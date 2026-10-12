<?php

use App\Enums\TestimonialStatus;
use App\Models\ActivityLog;
use App\Models\Testimonial;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

function testimonialBy(User $user, TestimonialStatus $status): Testimonial
{
    $testimonial = new Testimonial(['name' => $user->name, 'body' => 'A short testimonial about the programme.']);
    $testimonial->forceFill(['user_id' => $user->id, 'status' => $status, 'published_at' => $status === TestimonialStatus::Published ? now()->subDay() : null])->save();

    return $testimonial;
}

it('lets a registered visitor delete their account with their password', function () {
    $user = userWithRole('registered-user', twoFactor: false);
    $pending = testimonialBy($user, TestimonialStatus::Pending);
    $published = testimonialBy($user, TestimonialStatus::Published);
    $user->createToken('app');

    $this->actingAs($user)->deleteJson('/api/v1/me', ['password' => 'wrong'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();

    $this->actingAs($user)->deleteJson('/api/v1/me', ['password' => 'password'])->assertOk();

    expect(User::withTrashed()->whereKey($user->id)->exists())->toBeFalse()
        ->and(Testimonial::withTrashed()->whereKey($pending->id)->exists())->toBeFalse()
        ->and($published->fresh()->user_id)->toBeNull()
        ->and(PersonalAccessToken::query()->count())->toBe(0)
        ->and(ActivityLog::query()->where('action', 'user.self_deleted')->value('user_id'))->toBeNull();
    expect(ActivityLog::query()->where('action', 'user.self_deleted')->exists())->toBeTrue();
    $this->assertGuest('web');
});

it('refuses staff accounts and guests', function () {
    $this->deleteJson('/api/v1/me', ['password' => 'password'])->assertUnauthorized();

    $editor = userWithRole('editor');
    $this->actingAs($editor)->deleteJson('/api/v1/me', ['password' => 'password'])->assertForbidden();
    expect($editor->fresh())->not->toBeNull();
});

it('serves the search page without indexing', function () {
    $this->get('/search?q=water')->assertOk()->assertSee('noindex', false)->assertSee('id="pacms-initial"', false);
});
