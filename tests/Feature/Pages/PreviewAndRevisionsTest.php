<?php

use App\Enums\RevisionKind;
use App\Models\Revision;
use App\Services\Pages\PageService;
use Illuminate\Support\Facades\URL;

it('previews an unpublished draft only with a valid signature and permission', function () {
    $editor = userWithRole('editor');
    $page = makePage($editor, ['title' => 'Secret launch', 'excerpt' => 'Coming soon']);

    // Admin issues a signed, relative preview link.
    $link = $this->actingAs($editor)->get(route('admin.pages.preview', $page))->assertRedirect()->headers->get('Location');
    expect($link)->toContain('/preview/pages/'.$page->id)->toContain('signature=');

    $this->actingAs($editor)->get($link)
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('Secret launch')
        ->assertSee('"preview":true', false);

    expect($this->actingAs($editor)->get($link)->headers->get('Cache-Control'))->toContain('no-store');
});

it('rejects preview links without a signature, when expired, or for users without access', function () {
    $editor = userWithRole('editor');
    $page = makePage($editor, ['title' => 'Secret']);

    $this->actingAs($editor)->get('/preview/pages/'.$page->id)->assertForbidden();

    $expired = URL::temporarySignedRoute('preview.page', now()->subMinute(), ['page' => $page->id], absolute: false);
    $this->actingAs($editor)->get($expired)->assertForbidden();

    $valid = URL::temporarySignedRoute('preview.page', now()->addMinutes(10), ['page' => $page->id], absolute: false);
    $this->actingAs(userWithRole('registered-user', twoFactor: false))->get($valid)->assertForbidden();

    auth()->logout();
    $this->get($valid)->assertRedirect(route('login'));
});

it('lists, compares and restores revisions without rewriting history', function () {
    $editor = userWithRole('editor');
    $page = makePage($editor, ['title' => 'Version one']);

    $this->actingAs($editor)->put(route('admin.pages.update', $page), pagePayload([
        'title' => 'Version two', 'slug' => 'about-us', 'lock_version' => $page->fresh()->lock_version,
    ]));

    $this->actingAs($editor)->get(route('admin.pages.revisions', [$page, 'from' => 1, 'to' => 2]))
        ->assertOk()
        ->assertSee('Version one')
        ->assertSee('Version two');

    $first = Revision::where('revisionable_id', $page->id)->where('number', 1)->firstOrFail();
    $this->actingAs($editor)->post(route('admin.pages.revisions.restore', [$page, $first]))->assertRedirect(route('admin.pages.edit', $page));

    expect($page->fresh()->title)->toBe('Version one');
    $latest = Revision::where('revisionable_id', $page->id)->orderByDesc('number')->first();
    expect($latest->number)->toBe(3)->and($latest->kind)->toBe(RevisionKind::Restore);
});

it('does not let authors without revisions.restore restore revisions', function () {
    $author = userWithRole('author', twoFactor: false);
    $page = makePage($author);
    $revision = Revision::where('revisionable_id', $page->id)->firstOrFail();

    $this->actingAs($author)->post(route('admin.pages.revisions.restore', [$page, $revision]))->assertForbidden();
});

it('prunes old revisions but keeps published ones', function () {
    config(['pacms.revisions.keep' => 2]);
    $editor = userWithRole('editor');
    $page = livePage(['title' => 'Prune me'], $editor);

    foreach (range(1, 4) as $i) {
        app(PageService::class)->update($editor, $page->fresh(), ['title' => "Edit {$i}", 'template' => 'default'], $page->fresh()->lock_version);
    }

    $this->artisan('pacms:revisions:prune')->assertSuccessful();

    $kinds = Revision::where('revisionable_id', $page->id)->pluck('kind')->map->value;
    expect($kinds->filter(fn ($k) => $k === 'published'))->toHaveCount(1)
        ->and($kinds->reject(fn ($k) => $k === 'published'))->toHaveCount(2);
});
