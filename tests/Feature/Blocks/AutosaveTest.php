<?php

use App\Enums\RevisionKind;
use App\Models\Revision;
use App\Services\Revisions\RevisionService;

it('autosaves valid trees per user and offers them until the next real save', function () {
    $editor = userWithRole('editor');
    $page = livePage(['title' => 'Draft space']);
    $tree = [['uuid' => '01k00000000000000000000aut', 'type' => 'heading', 'content' => ['text' => 'Unsaved', 'level' => '2']]];

    $this->actingAs($editor)->postJson(route('admin.api.autosave'), ['owner' => 'page', 'id' => $page->id, 'blocks' => $tree])->assertOk();
    $this->actingAs($editor)->postJson(route('admin.api.autosave'), ['owner' => 'page', 'id' => $page->id, 'blocks' => $tree])->assertOk();

    expect(Revision::where('kind', RevisionKind::Autosave)->count())->toBe(1);

    $pending = app(RevisionService::class)->pendingAutosave($page->fresh(), $editor);
    expect($pending?->snapshot['blocks'][0]['content']['text'])->toBe('Unsaved');

    // Nothing changed in the working copy or live.
    $this->getJson('/api/v1/pages/draft-space')->assertJsonCount(0, 'data.blocks');

    // Other users do not see it.
    expect(app(RevisionService::class)->pendingAutosave($page->fresh(), userWithRole('editor')))->toBeNull();
});

it('refuses invalid trees and users who cannot edit the owner', function () {
    $page = livePage(['title' => 'Locked']);

    $this->actingAs(userWithRole('editor'))->postJson(route('admin.api.autosave'), ['owner' => 'page', 'id' => $page->id, 'blocks' => [
        ['uuid' => '01k00000000000000000000bad', 'type' => 'rich-text', 'content' => ['html' => ['x']]],
    ]])->assertUnprocessable();

    $this->actingAs(userWithRole('contributor', twoFactor: false))
        ->postJson(route('admin.api.autosave'), ['owner' => 'page', 'id' => $page->id, 'blocks' => []])
        ->assertForbidden();

    expect(Revision::where('kind', RevisionKind::Autosave)->count())->toBe(0);
});
