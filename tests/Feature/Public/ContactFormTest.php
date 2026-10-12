<?php

use App\Cms\Blocks\BlockPayloadResolver;
use App\Cms\Blocks\BlockTreeRepository;
use App\Mail\ContactMessageMail;
use App\Models\Block;
use App\Models\ContactMessage;
use App\Models\Page;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/** A published page with a Contact form block; returns the block. */
function contactBlock(array $content = []): Block
{
    $editor = userWithRole('editor');
    $page = makePage($editor, ['title' => 'Contact']);

    test()->actingAs($editor)->put(route('admin.pages.update', $page), pagePayload([
        'title' => $page->title,
        'slug' => $page->slug,
        'lock_version' => $page->fresh()->lock_version,
        'blocks' => json_encode([[
            'uuid' => '01k0000000000000000contact',
            'type' => 'contact-form',
            'content' => array_merge(['heading' => 'Write to us', 'recipient' => 'office@example.org', 'show_subject' => true, 'consent_text' => 'I agree that you keep my message.', 'button_label' => 'Send', 'thank_you' => 'Thanks, we will reply soon.'], $content),
        ]]),
    ]))->assertSessionHasNoErrors();
    auth()->logout();

    return Block::query()->where('uuid', '01k0000000000000000contact')->firstOrFail();
}

function contactInput(array $overrides = []): array
{
    return array_merge([
        'block' => '01k0000000000000000contact',
        'name' => 'Nadia Rahman',
        'email' => 'nadia@example.org',
        'subject' => 'Volunteering',
        'message' => 'I would like to help at the next event in Sylhet.',
        'consent' => true,
        'page' => '/contact',
        'elapsed' => 8000,
        'website' => '',
    ], $overrides);
}

beforeEach(function () {
    RateLimiter::clear('contact:127.0.0.1');
    RateLimiter::clear('contact:127.0.0.1:day');
});

it('keeps the message, mails the block recipient and answers with the thank-you text', function () {
    Mail::fake();
    contactBlock();

    $this->postJson('/api/v1/contact', contactInput())
        ->assertCreated()
        ->assertJsonPath('message', 'Thanks, we will reply soon.');

    $message = ContactMessage::query()->sole();
    expect($message->recipient)->toBe('office@example.org')
        ->and($message->page_url)->toBe('/contact')
        ->and($message->consent)->toBeTrue()
        ->and($message->mailed)->toBeTrue()
        ->and($message->read_at)->toBeNull();

    Mail::assertQueued(ContactMessageMail::class, fn ($mail) => $mail->hasTo('office@example.org') && $mail->hasReplyTo('nadia@example.org'));
});

it('never sends the recipient to the browser', function () {
    contactBlock();
    $tree = app(BlockTreeRepository::class)->load(Page::query()->where('title', 'Contact')->firstOrFail());
    $payload = app(BlockPayloadResolver::class)->resolve($tree);

    expect($tree[0]['content']['recipient'])->toBe('office@example.org')
        ->and($payload[0]['content'])->not->toHaveKey('recipient')
        ->and($payload[0]['content']['heading'])->toBe('Write to us')
        ->and($payload[0]['data'])->toBe(['has_consent' => true]);
});

it('validates the fields and requires consent when the block asks for it', function () {
    Mail::fake();
    contactBlock();

    $this->postJson('/api/v1/contact', contactInput(['name' => '', 'email' => 'nope', 'message' => 'short', 'consent' => false]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'message', 'consent']);

    expect(ContactMessage::count())->toBe(0);
});

it('does not ask for consent when the block has no consent text', function () {
    Mail::fake();
    contactBlock(['consent_text' => '']);

    $this->postJson('/api/v1/contact', contactInput(['consent' => null]))->assertCreated();
    expect(ContactMessage::query()->sole()->consent)->toBeFalse();
});

it('answers robots as if it worked, but keeps nothing', function (array $trap) {
    Mail::fake();
    contactBlock();

    $this->postJson('/api/v1/contact', contactInput($trap))->assertStatus(202);

    expect(ContactMessage::count())->toBe(0);
    Mail::assertNothingQueued();
})->with([
    'honeypot filled' => [['website' => 'http://spam.example']],
    'sent too fast' => [['elapsed' => 900]],
]);

it('limits messages per connection', function () {
    Mail::fake();
    contactBlock();

    foreach (range(1, 5) as $n) {
        $this->postJson('/api/v1/contact', contactInput())->assertCreated();
    }
    $this->postJson('/api/v1/contact', contactInput())->assertUnprocessable()->assertJsonValidationErrors('message');

    expect(ContactMessage::count())->toBe(5);
});

it('falls back to the site e-mail address for an unknown block', function () {
    Mail::fake();
    app(SettingsService::class)->set('site', ['contact_email' => 'hello@example.org']);

    $this->postJson('/api/v1/contact', contactInput(['block' => 'not-a-block']))->assertCreated();

    expect(ContactMessage::query()->sole()->recipient)->toBe('hello@example.org');
    Mail::assertQueued(ContactMessageMail::class, fn ($mail) => $mail->hasTo('hello@example.org'));
});

it('lets editors read, mark unread and delete messages, but not authors', function () {
    $message = ContactMessage::query()->create(['name' => 'Rahim', 'email' => 'rahim@example.org', 'message' => 'Hello there, a question.', 'recipient' => 'office@example.org']);

    $this->actingAs(userWithRole('author'))->get(route('admin.messages.index'))->assertForbidden();

    $editor = userWithRole('editor');
    $this->actingAs($editor)->get(route('admin.messages.index'))->assertOk()->assertSee('Rahim');
    $this->actingAs($editor)->get(route('admin.messages.show', $message))->assertOk()->assertSee('Hello there, a question.');
    expect($message->fresh()->read_at)->not->toBeNull();

    $this->actingAs($editor)->post(route('admin.messages.unread', $message))->assertRedirect(route('admin.messages.index'));
    expect($message->fresh()->read_at)->toBeNull();

    $this->actingAs($editor)->delete(route('admin.messages.destroy', $message))->assertRedirect(route('admin.messages.index'));
    expect(ContactMessage::count())->toBe(0);
});

it('removes messages after the retention period', function () {
    $old = ContactMessage::query()->create(['name' => 'Old', 'email' => 'old@example.org', 'message' => 'An old message here.', 'recipient' => 'x@example.org']);
    $old->forceFill(['created_at' => now()->subDays(ContactMessage::RETENTION_DAYS + 1)])->save();
    ContactMessage::query()->create(['name' => 'New', 'email' => 'new@example.org', 'message' => 'A new message here.', 'recipient' => 'x@example.org']);

    $this->artisan('model:prune', ['--model' => [ContactMessage::class]])->assertSuccessful();

    expect(ContactMessage::query()->pluck('name')->all())->toBe(['New']);
});
