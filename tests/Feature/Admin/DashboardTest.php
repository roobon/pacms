<?php

use App\Enums\WorkflowAction;
use App\Services\Publishing\PublishingService;

it('shows approvers the pages waiting for review', function () {
    $author = userWithRole('author', twoFactor: false);
    $page = makePage($author, ['title' => 'Please review me']);
    app(PublishingService::class)->transition($page, WorkflowAction::Submit, $author);

    $this->actingAs(userWithRole('editor'))
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Waiting for your review')
        ->assertSee('Please review me')
        ->assertDontSee('System health');
});

it('shows authors their own drafts but no review queue', function () {
    $author = userWithRole('author', twoFactor: false);
    makePage($author, ['title' => 'My unfinished page']);

    $this->actingAs($author)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Your drafts')
        ->assertSee('My unfinished page')
        ->assertDontSee('Waiting for your review');
});

it('only shows statistics the user may see', function () {
    $this->actingAs(userWithRole('moderator', twoFactor: false))
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('Media files')
        ->assertDontSee('Your drafts');

    $this->actingAs(userWithRole('administrator'))
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Users')
        ->assertSee('System health');
});
