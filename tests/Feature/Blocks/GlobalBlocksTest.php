<?php

use App\Enums\WorkflowAction;
use App\Models\ActivityLog;
use App\Models\ContentReference;
use App\Models\GlobalBlock;
use App\Models\Page;
use App\Services\Blocks\GlobalBlockService;
use App\Services\Publishing\PublishingService;

function ctaGlobal(string $text = 'Join us'): GlobalBlock
{
    $service = app(GlobalBlockService::class);
    $editor = userWithRole('editor');

    return $service->publish($editor, $service->create($editor, [
        'name' => 'Call to action',
        'blocks' => [['uuid' => '01k0000000000000000000gcta', 'type' => 'heading', 'content' => ['text' => $text, 'level' => '2']]],
    ]));
}

function pageWithGlobal(GlobalBlock $global): Page
{
    $page = livePage(['title' => 'About']);
    $editor = userWithRole('editor');

    test()->actingAs($editor)->put(route('admin.pages.update', $page), pagePayload([
        'title' => 'About', 'slug' => 'about', 'lock_version' => $page->fresh()->lock_version,
        'blocks' => json_encode([['uuid' => '01k0000000000000000000gref', 'type' => 'global-ref', 'global_block_id' => $global->id]]),
    ]))->assertSessionHasNoErrors();

    app(PublishingService::class)->transition($page->fresh(), WorkflowAction::Publish, $editor);

    return $page->fresh();
}

it('renders a global block on every page and propagates published changes', function () {
    $global = ctaGlobal('Join us');
    pageWithGlobal($global);

    $this->getJson('/api/v1/pages/about')
        ->assertOk()
        ->assertJsonPath('data.blocks.0.type', 'global-ref')
        ->assertJsonPath('data.blocks.0.children.0.content.text', 'Join us')
        ->assertJsonPath('data.blocks.0.children.0.locked', true);

    // Saving the working copy changes nothing live…
    $service = app(GlobalBlockService::class);
    $editor = userWithRole('editor');
    $service->update($editor, $global, ['name' => 'Call to action', 'blocks' => [
        ['uuid' => '01k0000000000000000000gcta', 'type' => 'heading', 'content' => ['text' => 'Volunteer today', 'level' => '2']],
    ]], $global->fresh()->lock_version);
    $this->getJson('/api/v1/pages/about')->assertJsonPath('data.blocks.0.children.0.content.text', 'Join us');

    // …publishing updates the page without republishing it (cache invalidated).
    $service->publish($editor, $global->fresh());
    $this->getJson('/api/v1/pages/about')->assertJsonPath('data.blocks.0.children.0.content.text', 'Volunteer today');
});

it('tracks where a global block is used and refuses to delete it while used', function () {
    $global = ctaGlobal();
    $page = pageWithGlobal($global);

    expect(ContentReference::where('target_type', 'global_block')->where('target_id', $global->id)->where('owner_id', $page->id)->exists())->toBeTrue();

    $this->actingAs(userWithRole('editor'))
        ->delete(route('admin.global-blocks.destroy', $global))
        ->assertSessionHasErrors('global_block');
    expect($global->fresh())->not->toBeNull();
});

it('rejects global blocks inside global blocks and references to missing ones', function () {
    $global = ctaGlobal();
    $editor = userWithRole('editor');

    $this->actingAs($editor)->put(route('admin.global-blocks.update', $global), [
        'name' => 'Loop', 'lock_version' => $global->lock_version,
        'blocks' => json_encode([['uuid' => '01k000000000000000000000gg', 'type' => 'global-ref', 'global_block_id' => $global->id]]),
    ])->assertSessionHasErrors('blocks.01k000000000000000000000gg');

    $this->actingAs($editor)->postJson(route('admin.api.blocks.resolve'), ['blocks' => [
        ['uuid' => '01k000000000000000000000gm', 'type' => 'global-ref', 'global_block_id' => 999999],
    ]])->assertJsonValidationErrors('blocks.01k000000000000000000000gm');
});

it('detaches a copy of the published tree and logs it', function () {
    $global = ctaGlobal('Detached text');

    $this->actingAs(userWithRole('editor'))
        ->postJson(route('admin.api.globals.detach', $global))
        ->assertOk()
        ->assertJsonPath('blocks.0.content.text', 'Detached text');

    expect(ActivityLog::where('action', 'global_block.detached')->exists())->toBeTrue();
    $this->actingAs(userWithRole('author', twoFactor: false))->postJson(route('admin.api.globals.detach', $global))->assertForbidden();
});

it('converts builder blocks into a published global block', function () {
    $this->actingAs(userWithRole('editor'))
        ->postJson(route('admin.api.globals.store'), ['name' => 'Footer note', 'blocks' => [
            ['type' => 'rich-text', 'content' => ['html' => '<p>Thanks</p>']],
        ]])
        ->assertCreated();

    expect(GlobalBlock::where('name', 'Footer note')->first()?->isPublished())->toBeTrue();
    $this->actingAs(userWithRole('editor'))->getJson(route('admin.api.globals.index'))->assertJsonPath('data.0.name', 'Footer note');
});

it('limits global block management to permitted roles', function () {
    $this->actingAs(userWithRole('author', twoFactor: false))->get(route('admin.global-blocks.index'))->assertForbidden();
    $this->actingAs(userWithRole('editor'))->get(route('admin.global-blocks.index'))->assertOk();
    $this->actingAs(userWithRole('editor'))->get(route('admin.global-blocks.create'))->assertOk();
});
