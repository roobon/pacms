<?php

use App\Enums\ContentStatus;
use App\Models\ActivityLog;
use App\Models\Page;
use App\Models\Revision;

it('lets an author create a draft page with an automatic slug, revision and log entry', function () {
    $author = userWithRole('author', twoFactor: false);

    $this->actingAs($author)->post(route('admin.pages.store'), pagePayload())->assertRedirect();

    $page = Page::firstOrFail();
    expect($page->slug)->toBe('our-programs')
        ->and($page->path)->toBe('our-programs')
        ->and($page->status)->toBe(ContentStatus::Draft)
        ->and($page->author_id)->toBe($author->id)
        ->and($page->isLive())->toBeFalse()
        ->and(Revision::where('revisionable_id', $page->id)->count())->toBe(1)
        ->and(ActivityLog::where('action', 'page.created')->exists())->toBeTrue();
});

it('builds hierarchical paths and rebuilds them when a parent moves', function () {
    $editor = userWithRole('editor');
    $about = makePage($editor, ['title' => 'About']);
    $team = makePage($editor, ['title' => 'Team', 'parent_id' => $about->id]);

    expect($team->path)->toBe('about/team');

    $this->actingAs($editor)->put(route('admin.pages.update', $about), pagePayload([
        'title' => 'About', 'slug' => 'who-we-are', 'lock_version' => $about->fresh()->lock_version,
    ]))->assertSessionHasNoErrors();

    expect($team->fresh()->path)->toBe('who-we-are/team');
});

it('rejects reserved, invalid and duplicate URLs', function (array $data, string $field) {
    $editor = userWithRole('editor');
    makePage($editor, ['title' => 'Contact']);

    $this->actingAs($editor)->post(route('admin.pages.store'), pagePayload($data))->assertSessionHasErrors($field);
})->with([
    'reserved' => [['title' => 'Admin', 'slug' => 'admin'], 'slug'],
    'reserved api' => [['title' => 'News', 'slug' => 'news'], 'slug'],
    'uppercase/space' => [['title' => 'X', 'slug' => 'Hello World'], 'slug'],
    'duplicate' => [['title' => 'Contact again', 'slug' => 'contact'], 'slug'],
]);

it('prevents placing a page under its own sub-page', function () {
    $editor = userWithRole('editor');
    $parent = makePage($editor, ['title' => 'Parent']);
    $child = makePage($editor, ['title' => 'Child', 'parent_id' => $parent->id]);

    $this->actingAs($editor)->put(route('admin.pages.update', $parent), pagePayload([
        'title' => 'Parent', 'slug' => 'parent', 'parent_id' => $child->id, 'lock_version' => $parent->fresh()->lock_version,
    ]))->assertSessionHasErrors('parent_id');
});

it('detects concurrent edits with the lock version', function () {
    $editor = userWithRole('editor');
    $page = makePage($editor);
    $stale = $page->lock_version;

    $this->actingAs($editor)->put(route('admin.pages.update', $page), pagePayload(['title' => 'First edit', 'slug' => 'about-us', 'lock_version' => $stale]))
        ->assertSessionHasNoErrors();

    $this->actingAs($editor)->put(route('admin.pages.update', $page), pagePayload(['title' => 'Second edit', 'slug' => 'about-us', 'lock_version' => $stale]))
        ->assertSessionHasErrors('lock_version');

    expect($page->fresh()->title)->toBe('First edit');
});

it('only lets authors edit their own draft or published pages', function () {
    $author = userWithRole('author', twoFactor: false);
    $other = makePage(userWithRole('author', twoFactor: false), ['title' => 'Not mine']);
    $mine = makePage($author, ['title' => 'Mine']);

    $this->actingAs($author)->get(route('admin.pages.edit', $mine))->assertOk()->assertSee('Save draft');
    $this->actingAs($author)->put(route('admin.pages.update', $other), pagePayload(['lock_version' => 0]))->assertForbidden();

    $mine->forceFill(['status' => ContentStatus::InReview])->save();
    $this->actingAs($author)->put(route('admin.pages.update', $mine), pagePayload(['title' => 'Mine', 'slug' => 'mine', 'lock_version' => $mine->lock_version]))
        ->assertForbidden();
});

it('lets viewers without edit rights open but not change a page', function () {
    $page = makePage(userWithRole('editor'));
    $contributor = userWithRole('contributor', twoFactor: false);

    $this->actingAs($contributor)->get(route('admin.pages.edit', $page))->assertOk()->assertDontSee('Save draft');
});

it('refuses to delete a page that has sub-pages, and unpublishes live pages on delete', function () {
    $editor = userWithRole('editor');
    $parent = livePage(['title' => 'Parent']);
    makePage($editor, ['title' => 'Child', 'parent_id' => $parent->id]);

    $this->actingAs($editor)->delete(route('admin.pages.destroy', $parent))->assertSessionHasErrors('page');

    $solo = livePage(['title' => 'Solo']);
    $this->actingAs($editor)->delete(route('admin.pages.destroy', $solo))->assertRedirect(route('admin.pages.index'));

    expect(Page::withTrashed()->find($solo->id)->published_path)->toBeNull();
    $this->get('/solo')->assertNotFound();
});

it('lists pages with search and status filters', function () {
    $editor = userWithRole('editor');
    makePage($editor, ['title' => 'Findable page']);

    $this->actingAs($editor)->get(route('admin.pages.index', ['q' => 'Findable']))->assertOk()->assertSee('Findable page');
    $this->actingAs($editor)->get(route('admin.pages.index', ['status' => 'published']))->assertOk()->assertDontSee('Findable page');
});
