<?php

use App\Cms\Blocks\BlockPayloadResolver;
use App\Cms\Blocks\BlockTreeValidator;
use App\Enums\TestimonialAction;
use App\Enums\TestimonialStatus;
use App\Models\Program;
use App\Models\Testimonial;
use App\Models\TestimonialModerationLog;
use App\Models\User;
use App\Services\Testimonials\TestimonialModerationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake((string) config('pacms.media.disk'));
    Storage::fake((string) config('pacms.media.private_disk'));
});

function registeredUser(bool $verified = true): User
{
    $user = ($verified ? User::factory() : User::factory()->unverified())->create(['email' => 'rahim-'.uniqid().'@example.org']);
    $user->assignRole('registered-user');

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function testimonialInput(array $overrides = []): array
{
    return array_merge([
        'name' => 'Rahim Uddin',
        'organization' => 'Dhaka Model School',
        'designation' => 'Teacher',
        'body' => 'The Eco-Schools programme changed how our students think about waste and water.',
        'consent' => '1',
    ], $overrides);
}

function submitted(?User $user = null): Testimonial
{
    test()->actingAs($user ?? registeredUser())->post('/api/v1/testimonials', testimonialInput(), ['Accept' => 'application/json'])->assertCreated();

    return Testimonial::query()->latest('id')->firstOrFail();
}

it('accepts submissions only from signed-in users with a verified e-mail address and consent', function () {
    $this->postJson('/api/v1/testimonials', testimonialInput())->assertUnauthorized();
    $this->actingAs(registeredUser(verified: false))->postJson('/api/v1/testimonials', testimonialInput())->assertForbidden();

    $user = registeredUser();
    $this->actingAs($user)->postJson('/api/v1/testimonials', testimonialInput(['consent' => '0']))->assertUnprocessable()->assertJsonValidationErrors('consent');
    $this->actingAs($user)->postJson('/api/v1/testimonials', testimonialInput(['body' => str_repeat('a', 1501)]))->assertJsonValidationErrors('body');
    $this->actingAs($user)->postJson('/api/v1/testimonials', testimonialInput(['photo' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')]))->assertJsonValidationErrors('photo');
    $this->actingAs($user)->postJson('/api/v1/testimonials', testimonialInput(['photo' => UploadedFile::fake()->image('big.jpg')->size(3000)]))->assertJsonValidationErrors('photo');
    // Only published programs can be chosen.
    $draft = Program::query()->forceCreate(['title' => 'Secret plan', 'slug' => 'secret-plan', 'status' => 'draft']);
    $this->actingAs($user)->postJson('/api/v1/testimonials', testimonialInput(['program_id' => $draft->id]))->assertJsonValidationErrors('program_id');

    $this->actingAs($user)->post('/api/v1/testimonials', testimonialInput(['body' => '<b>Great</b> work, really great work by everyone.', 'photo' => fakeJpeg('me.jpg', 800, 800)]), ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonMissingPath('data.user_id');

    $testimonial = Testimonial::query()->firstOrFail();
    expect($testimonial->status)->toBe(TestimonialStatus::Pending)
        ->and($testimonial->user_id)->toBe($user->id)
        ->and($testimonial->body)->toBe('Great work, really great work by everyone.')
        ->and($testimonial->consent_version)->toBe('1.0')
        ->and($testimonial->consent_given_at)->not->toBeNull()
        ->and($testimonial->submittedIp())->toBe('127.0.0.1')
        // The photo stays private until the testimonial is published.
        ->and($testimonial->photo->isPublic())->toBeFalse()
        ->and($testimonial->revisions()->first()->summary)->toBe('Original submission')
        ->and(TestimonialModerationLog::query()->where('testimonial_id', $testimonial->id)->value('to_status'))->toBe(TestimonialStatus::Pending);
});

it('limits submissions to 3 a day per account and quietly drops honeypot submissions', function () {
    $user = registeredUser();
    // Mistakes in the form do not use up the quota.
    $this->actingAs($user)->postJson('/api/v1/testimonials', testimonialInput(['consent' => '0']))->assertUnprocessable();
    $this->actingAs($user)->postJson('/api/v1/testimonials', testimonialInput(['body' => 'Too short']))->assertUnprocessable();
    foreach (range(1, 3) as $i) {
        $this->actingAs($user)->postJson('/api/v1/testimonials', testimonialInput())->assertCreated();
    }
    $this->actingAs($user)->postJson('/api/v1/testimonials', testimonialInput())->assertTooManyRequests();

    $bot = registeredUser();
    $this->actingAs($bot)->postJson('/api/v1/testimonials', testimonialInput(['homepage' => 'https://spam.example']))->assertCreated();
    expect(Testimonial::query()->where('user_id', $bot->id)->exists())->toBeFalse();
});

it('moves through moderation step by step, each step logged and permission-checked', function () {
    $testimonial = submitted();
    $moderator = userWithRole('moderator');
    $service = app(TestimonialModerationService::class);

    // Publishing straight from the queue is not a step that exists.
    $this->actingAs($moderator)->post(route('admin.testimonials.moderate', $testimonial), ['action' => 'publish'])->assertSessionHasErrors('action');
    // Users without testimonial permissions cannot moderate.
    expect(fn () => $service->transition($testimonial, TestimonialAction::Approve, userWithRole('author', twoFactor: false)))
        ->toThrow(AuthorizationException::class);

    $this->actingAs($moderator)->post(route('admin.testimonials.moderate', $testimonial), ['action' => 'start_review'])->assertRedirect();
    $this->actingAs($moderator)->post(route('admin.testimonials.moderate', $testimonial), ['action' => 'approve'])->assertRedirect();
    expect($testimonial->fresh()->status)->toBe(TestimonialStatus::Approved)
        ->and($testimonial->fresh()->isPublished())->toBeFalse();

    $this->actingAs($moderator)->post(route('admin.testimonials.moderate', $testimonial), ['action' => 'publish'])->assertRedirect();
    $testimonial->refresh();
    expect($testimonial->isPublished())->toBeTrue();

    $this->actingAs($moderator)->post(route('admin.testimonials.moderate', $testimonial), ['action' => 'unpublish'])->assertRedirect();
    expect($testimonial->fresh()->status)->toBe(TestimonialStatus::Approved);

    expect(TestimonialModerationLog::query()->where('testimonial_id', $testimonial->id)->orderBy('id')->pluck('to_status')->map->value->all())
        ->toBe(['pending', 'under_review', 'approved', 'published', 'approved']);

    // The moderator role may not delete.
    $this->actingAs($moderator)->delete(route('admin.testimonials.destroy', $testimonial))->assertForbidden();
    $this->actingAs(userWithRole('editor'))->delete(route('admin.testimonials.destroy', $testimonial))->assertRedirect();
    expect(Testimonial::query()->count())->toBe(0);
});

it('requires an internal reason to reject, shown to the submitter only when the site allows it', function () {
    $user = registeredUser();
    $testimonial = submitted($user);
    $moderator = userWithRole('moderator');

    $this->actingAs($moderator)->post(route('admin.testimonials.moderate', $testimonial), ['action' => 'reject'])->assertSessionHasErrors('note');
    $this->actingAs($moderator)->post(route('admin.testimonials.moderate', $testimonial), ['action' => 'reject', 'note' => 'Advertises a private business'])->assertRedirect();
    expect($testimonial->fresh()->status)->toBe(TestimonialStatus::Rejected)
        ->and($testimonial->fresh()->rejection_reason)->toBe('Advertises a private business');

    $this->actingAs($user)->getJson('/api/v1/me/testimonials')
        ->assertJsonPath('data.0.status_label', 'Not accepted')
        ->assertDontSee('Advertises');

    config(['pacms.testimonials.show_rejection_reason' => true]);
    $this->actingAs($user)->getJson('/api/v1/me/testimonials')->assertJsonPath('data.0.rejection_reason', 'Advertises a private business');

    // Other people's submissions are not listed.
    $this->actingAs(registeredUser())->getJson('/api/v1/me/testimonials')->assertJsonCount(0, 'data');
});

it('keeps the original submission when staff edit it, and logs the edit', function () {
    $testimonial = submitted();
    $editor = userWithRole('editor');

    $this->actingAs($editor)->put(route('admin.testimonials.update', $testimonial), testimonialInput([
        'lock_version' => $testimonial->lock_version, 'body' => 'The Eco-Schools programme changed how our students think about waste.', 'featured' => '1',
    ]))->assertSessionHasNoErrors();

    expect($testimonial->revisions()->count())->toBe(2)
        ->and(TestimonialModerationLog::query()->where('testimonial_id', $testimonial->id)->where('note', 'Edited')->exists())->toBeTrue();
    $this->actingAs($editor)->get(route('admin.testimonials.edit', $testimonial))->assertOk()
        ->assertSee('Original submission')
        ->assertSee('think about waste and water.');

    // Staff-entered testimonials start as drafts and can be approved directly.
    $this->actingAs($editor)->get(route('admin.testimonials.create'))->assertOk()->assertSee('Save draft');
    $this->actingAs($editor)->post(route('admin.testimonials.store'), testimonialInput(['name' => 'Head teacher']))->assertSessionHasNoErrors();
    $draft = Testimonial::query()->where('name', 'Head teacher')->firstOrFail();
    expect($draft->status)->toBe(TestimonialStatus::Draft)->and($draft->user_id)->toBeNull();
    $this->actingAs($editor)->post(route('admin.testimonials.moderate', $draft), ['action' => 'approve'])->assertRedirect();
    expect($draft->fresh()->status)->toBe(TestimonialStatus::Approved);
});

it('never exposes private data in the API or in blocks', function () {
    $user = registeredUser();
    $this->actingAs($user)->post('/api/v1/testimonials', testimonialInput(['photo' => fakeJpeg('face.jpg', 600, 600)]), ['Accept' => 'application/json'])->assertCreated();
    $testimonial = Testimonial::query()->firstOrFail();
    $moderator = userWithRole('moderator');
    $service = app(TestimonialModerationService::class);
    $service->transition($testimonial, TestimonialAction::Approve, $moderator);
    $service->transition($testimonial, TestimonialAction::Publish, $moderator);
    expect($testimonial->photo()->first()->isPublic())->toBeTrue();
    // A rejected one never appears.
    $other = submitted(registeredUser());
    $service->transition($other, TestimonialAction::Reject, $moderator, 'Off-topic');

    auth()->forgetGuards();
    $response = $this->getJson('/api/v1/testimonials')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Rahim Uddin')
        ->assertJsonPath('data.0.organization', 'Dhaka Model School');
    $clean = app(BlockTreeValidator::class)->validate([['type' => 'testimonials', 'display' => ['mode' => 'quote-slider']]], userWithRole('super-admin'));
    $block = json_encode(app(BlockPayloadResolver::class)->resolve($clean));

    foreach ([$response->getContent(), $block] as $json) {
        expect($json)->not->toContain($user->email)
            ->not->toContain('user_id')
            ->not->toContain('127.0.0.1')
            ->not->toContain('consent')
            ->not->toContain('rejection')
            ->not->toContain('Off-topic')
            ->not->toContain('submitted_ip');
    }
    expect(json_decode($block, true)[0]['items'][0]['meta']['quote'])->toContain('Eco-Schools');
});

it('shows the moderation queue with a count and forgets IP addresses after 90 days', function () {
    submitted();
    $moderator = userWithRole('moderator');

    $this->actingAs($moderator)->get(route('admin.testimonials.index'))->assertOk()
        ->assertSee('Rahim Uddin')
        ->assertSee('waiting for moderation');
    $this->actingAs(userWithRole('author', twoFactor: false))->get(route('admin.testimonials.index'))->assertForbidden();

    Testimonial::query()->update(['created_at' => now()->subDays(91)]);
    $this->artisan('pacms:testimonials:purge-ips')->assertSuccessful();
    expect(Testimonial::query()->first()->submittedIp())->toBeNull();
});

it('refuses steps that do not fit the current status', function () {
    $testimonial = submitted();
    $service = app(TestimonialModerationService::class);

    expect(fn () => $service->transition($testimonial, TestimonialAction::Unpublish, userWithRole('editor')))->toThrow(ValidationException::class);
    expect(array_map(fn ($a) => $a->value, $service->availableActions($testimonial, userWithRole('moderator'))))->toBe(['start_review', 'approve', 'reject']);
});
