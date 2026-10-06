<?php

use App\Enums\ContentStatus;
use App\Enums\WorkflowAction;
use App\Models\ActivityLog;
use App\Models\Page;
use App\Models\Redirect;
use App\Services\Publishing\PublishingService;

function workflow(Page $page, string $action, array $extra = []): array
{
    return array_merge(['action' => $action], $extra);
}

it('runs the full review workflow with the right roles', function () {
    $author = userWithRole('author', twoFactor: false);
    $editor = userWithRole('editor');
    $page = makePage($author, ['title' => 'Our Work']);

    // Author submits.
    $this->actingAs($author)->post(route('admin.pages.workflow', $page), workflow($page, 'submit'))->assertSessionHasNoErrors();
    expect($page->fresh()->status)->toBe(ContentStatus::InReview);

    // Author cannot approve or publish.
    $this->actingAs($author)->post(route('admin.pages.workflow', $page), workflow($page, 'approve'))->assertForbidden();
    $this->actingAs($author)->post(route('admin.pages.workflow', $page), workflow($page, 'publish'))->assertForbidden();

    // Editor approves and publishes.
    $this->actingAs($editor)->post(route('admin.pages.workflow', $page), workflow($page, 'approve'))->assertSessionHasNoErrors();
    $this->actingAs($editor)->post(route('admin.pages.workflow', $page), workflow($page, 'publish'))->assertSessionHasNoErrors();

    $page->refresh();
    expect($page->isLive())->toBeTrue()
        ->and($page->published_path)->toBe('our-work')
        ->and($page->has_unpublished_changes)->toBeFalse()
        ->and(ActivityLog::where('action', 'workflow.publish')->exists())->toBeTrue();

    $this->get('/our-work')->assertOk()->assertSee('<title data-pacms-head>Our Work', false);
});

it('keeps the live version online while the working copy is edited (staged publishing)', function () {
    $editor = userWithRole('editor');
    $page = livePage(['title' => 'Original title']);

    $this->actingAs($editor)->put(route('admin.pages.update', $page), pagePayload([
        'title' => 'Draft title', 'slug' => 'original-title', 'lock_version' => $page->lock_version,
    ]))->assertSessionHasNoErrors();

    $page->refresh();
    expect($page->status)->toBe(ContentStatus::Draft)
        ->and($page->has_unpublished_changes)->toBeTrue()
        ->and($page->isLive())->toBeTrue();

    $this->get('/original-title')->assertOk()->assertSee('Original title')->assertDontSee('Draft title');
    $this->getJson('/api/v1/pages/original-title')->assertJsonPath('data.title', 'Original title');

    app(PublishingService::class)->transition($page, WorkflowAction::Publish, $editor);
    $this->getJson('/api/v1/pages/original-title')->assertJsonPath('data.title', 'Draft title');
});

it('never exposes unpublished, archived or deleted pages', function () {
    $editor = userWithRole('editor');
    makePage($editor, ['title' => 'Secret draft']);

    $this->get('/secret-draft')->assertNotFound();
    $this->getJson('/api/v1/pages/secret-draft')->assertNotFound()->assertJsonPath('code', 'not_found');
    $this->getJson('/api/v1/resolve?path=/secret-draft')->assertNotFound()->assertJsonPath('kind', 'not_found');

    $archived = livePage(['title' => 'Old news']);
    app(PublishingService::class)->transition($archived, WorkflowAction::Archive, $editor);
    $this->get('/old-news')->assertNotFound();
    $this->actingAs($editor)->get(route('admin.pages.edit', $archived))->assertSee('This page is archived');
});

it('requires the parent to be live before a sub-page can be published', function () {
    $editor = userWithRole('editor');
    $parent = makePage($editor, ['title' => 'About']);
    $child = makePage($editor, ['title' => 'Team', 'parent_id' => $parent->id]);

    $this->actingAs($editor)->post(route('admin.pages.workflow', $child), workflow($child, 'publish'))->assertSessionHasErrors('action');

    app(PublishingService::class)->transition($parent, WorkflowAction::Publish, $editor);
    $this->actingAs($editor)->post(route('admin.pages.workflow', $child), workflow($child, 'publish'))->assertSessionHasNoErrors();

    $this->get('/about/team')->assertOk();

    // A live parent with live sub-pages cannot be unpublished.
    $this->actingAs($editor)->post(route('admin.pages.workflow', $parent), workflow($parent, 'unpublish'))->assertSessionHasErrors('action');
});

it('creates redirects when a published page and its sub-pages move', function () {
    $editor = userWithRole('editor');
    $parent = livePage(['title' => 'About']);
    $child = livePage(['title' => 'Team', 'parent_id' => $parent->id]);

    $this->actingAs($editor)->put(route('admin.pages.update', $parent), pagePayload([
        'title' => 'About', 'slug' => 'who-we-are', 'lock_version' => $parent->fresh()->lock_version,
    ]));
    app(PublishingService::class)->transition($parent->fresh(), WorkflowAction::Publish, $editor);

    expect($child->fresh()->published_path)->toBe('who-we-are/team')
        ->and(Redirect::where('source_path', '/about')->value('target_path'))->toBe('/who-we-are')
        ->and(Redirect::where('source_path', '/about/team')->value('target_path'))->toBe('/who-we-are/team');

    $this->get('/about/team')->assertRedirect('/who-we-are/team')->assertStatus(301);
    $this->get('/who-we-are/team')->assertOk();
    expect(Redirect::where('source_path', '/about/team')->value('hits'))->toBe(1);
});

it('publishes scheduled pages when their time comes', function () {
    $editor = userWithRole('editor');
    $page = makePage($editor, ['title' => 'Launch']);

    $this->actingAs($editor)->post(route('admin.pages.workflow', $page), workflow($page, 'schedule', [
        'publish_at' => now()->addHour()->timezone('Asia/Dhaka')->format('Y-m-d\TH:i'),
    ]))->assertSessionHasNoErrors();

    expect($page->fresh()->status)->toBe(ContentStatus::Approved)->and($page->fresh()->publish_at)->not->toBeNull();

    $this->artisan('pacms:publish-scheduled');
    expect($page->fresh()->isLive())->toBeFalse();

    $this->travel(61)->minutes();
    $this->artisan('pacms:publish-scheduled');
    expect($page->fresh()->isLive())->toBeTrue();
});

it('rejects scheduling in the past', function () {
    $editor = userWithRole('editor');
    $page = makePage($editor);

    $this->actingAs($editor)->post(route('admin.pages.workflow', $page), workflow($page, 'schedule', [
        'publish_at' => now()->subDay()->format('Y-m-d\TH:i'),
    ]))->assertSessionHasErrors('publish_at');
});

it('offers only the actions a role may perform', function () {
    $page = makePage(userWithRole('editor'));
    $publishing = app(PublishingService::class);

    $contributorActions = array_map(fn ($a) => $a->value, $publishing->availableActions($page, userWithRole('contributor', twoFactor: false)));
    $editorActions = array_map(fn ($a) => $a->value, $publishing->availableActions($page, userWithRole('editor')));

    expect($contributorActions)->toBe([])
        ->and($editorActions)->toContain('publish', 'schedule', 'archive')
        ->and($editorActions)->not->toContain('unpublish', 'approve');
});

it('lets editors hide the page title, staged like any other change', function () {
    $editor = userWithRole('editor');
    $page = livePage(['title' => 'About us']);
    $this->getJson('/api/v1/pages/about-us')->assertJsonPath('data.show_title', true);

    $this->actingAs($editor)->put(route('admin.pages.update', $page), pagePayload([
        'title' => 'About us', 'slug' => 'about-us', 'lock_version' => $page->lock_version, 'show_title' => '0',
    ]))->assertSessionHasNoErrors();

    expect($page->fresh()->show_title)->toBeFalse();
    $this->getJson('/api/v1/pages/about-us')->assertJsonPath('data.show_title', true);

    app(PublishingService::class)->transition($page->fresh(), WorkflowAction::Publish, $editor);
    $this->getJson('/api/v1/pages/about-us')->assertJsonPath('data.show_title', false);

    // Requests without the field (e.g. older clients) keep the title visible.
    $page->refresh();
    $this->actingAs($editor)->put(route('admin.pages.update', $page), pagePayload([
        'title' => 'About us', 'slug' => 'about-us', 'lock_version' => $page->lock_version,
    ]))->assertSessionHasNoErrors();
    expect($page->fresh()->show_title)->toBeTrue();

    // Snapshots from before the field existed restore with the title shown.
    $page->refresh();
    $page->show_title = false;
    $page->applySnapshot(['fields' => ['title' => 'About us']]);
    expect($page->show_title)->toBeTrue();
});
