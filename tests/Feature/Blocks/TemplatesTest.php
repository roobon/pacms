<?php

use App\Cms\Blocks\BlockTreeRepository;
use App\Models\BlockTemplate;
use App\Services\Blocks\BlockTemplateService;

it('saves a template from the builder and serves it for insertion', function () {
    $editor = userWithRole('editor');

    $id = $this->actingAs($editor)->postJson(route('admin.api.templates.store'), [
        'name' => 'Hero default', 'scope' => 'section', 'category' => 'Heroes',
        'blocks' => [['type' => 'section', 'children' => [['type' => 'heading', 'content' => ['text' => 'Title', 'level' => '2']]]]],
    ])->assertCreated()->json('data.id');

    $this->actingAs($editor)->getJson(route('admin.api.templates.index'))->assertJsonPath('data.0.name', 'Hero default');
    $this->actingAs($editor)->getJson(route('admin.api.templates.show', $id))
        ->assertOk()
        ->assertJsonPath('blocks.0.type', 'section')
        ->assertJsonPath('blocks.0.children.0.content.text', 'Title');
});

it('validates template trees and requires the permission', function () {
    $this->actingAs(userWithRole('author', twoFactor: false))->postJson(route('admin.api.templates.store'), [
        'name' => 'X', 'scope' => 'block', 'blocks' => [['type' => 'divider']],
    ])->assertForbidden();

    $this->actingAs(userWithRole('editor'))->postJson(route('admin.api.templates.store'), [
        'name' => 'Bad', 'scope' => 'block', 'blocks' => [['uuid' => '01k0000000000000000000badd', 'type' => 'rich-text', 'content' => ['html' => 5]]],
    ])->assertJsonValidationErrors('blocks.01k0000000000000000000badd.content.html');
});

it('hides draft templates from the palette and keeps pages unchanged when a template changes', function () {
    $editor = userWithRole('editor');
    $service = app(BlockTemplateService::class);
    $template = $service->create($editor, ['name' => 'Note', 'scope' => 'block', 'status' => 'draft', 'blocks' => [['type' => 'divider']]]);

    $this->actingAs($editor)->getJson(route('admin.api.templates.index'))->assertJsonCount(0, 'data');
    $this->actingAs($editor)->getJson(route('admin.api.templates.show', $template))->assertNotFound();

    // Template edits are independent of anything inserted from it earlier.
    $page = livePage(['title' => 'Copy target']);
    $this->actingAs($editor)->put(route('admin.pages.update', $page), pagePayload([
        'title' => 'Copy target', 'slug' => 'copy-target', 'lock_version' => $page->lock_version,
        'blocks' => json_encode([['uuid' => '01k0000000000000000000copy', 'type' => 'divider']]),
    ]))->assertSessionHasNoErrors();
    $service->update($editor, $template->fresh(), ['name' => 'Note', 'scope' => 'block', 'status' => 'published', 'blocks' => [['type' => 'spacer']]], $template->fresh()->lock_version);

    expect(app(BlockTreeRepository::class)->load($page)[0]['type'])->toBe('divider')
        ->and(BlockTemplate::find($template->id)->status)->toBe('published');
});

it('manages templates in the admin', function () {
    $editor = userWithRole('editor');

    $this->actingAs($editor)->post(route('admin.block-templates.store'), [
        'name' => 'Two columns', 'scope' => 'section', 'status' => 'published',
        'blocks' => json_encode([['type' => 'columns', 'layout' => ['columns' => ['desktop' => [6, 6]]], 'children' => [['type' => 'column'], ['type' => 'column']]]]),
    ])->assertRedirect();

    $template = BlockTemplate::where('name', 'Two columns')->firstOrFail();
    $this->actingAs($editor)->get(route('admin.block-templates.edit', $template))->assertOk()->assertSee('Template content');
    $this->actingAs($editor)->get(route('admin.block-templates.index'))->assertOk()->assertSee('Two columns');
});
