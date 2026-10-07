<?php

use App\Models\Block;

it('serves block definitions, tokens and sources to staff only', function () {
    $this->actingAs(userWithRole('editor'))
        ->getJson(route('admin.api.blocks.definitions'))
        ->assertOk()
        ->assertJsonStructure(['types' => [['slug', 'label', 'category', 'icon', 'fields', 'capabilities', 'defaults']], 'display_modes', 'sources' => ['news'], 'tokens' => ['color', 'space'], 'permissions'])
        ->assertJsonPath('permissions.custom_attributes', false);

    $this->actingAs(userWithRole('moderator', twoFactor: false))->getJson(route('admin.api.blocks.definitions'))->assertForbidden();
});

it('validates and resolves unsaved trees for the live preview without storing them', function () {
    $editor = userWithRole('editor');

    $this->actingAs($editor)->postJson(route('admin.api.blocks.resolve'), ['blocks' => [
        ['uuid' => '01k00000000000000000000aaa', 'type' => 'heading', 'hidden' => true, 'content' => ['text' => 'Preview me', 'level' => '2']],
    ]])
        ->assertOk()
        ->assertJsonPath('blocks.0.content.text', 'Preview me')
        ->assertJsonPath('blocks.0.hidden', true);

    expect(Block::count())->toBe(0);

    $this->actingAs($editor)->postJson(route('admin.api.blocks.resolve'), ['blocks' => [
        ['uuid' => '01k00000000000000000000bbb', 'type' => 'heading', 'content' => ['text' => '']],
    ]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('blocks.01k00000000000000000000bbb.content.text');
});

it('searches link targets', function () {
    livePage(['title' => 'Contact']);

    $this->actingAs(userWithRole('editor'))
        ->getJson(route('admin.api.link-targets', ['q' => 'Cont']))
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Contact')
        ->assertJsonPath('data.0.entity', 'pages');
});

it('serves the builder preview frame to signed-in staff only, never indexed', function () {
    $this->get('/__builder-preview')->assertRedirect(route('login'));

    $this->actingAs(userWithRole('editor'))
        ->get('/__builder-preview')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('"builder":true', false);

    $this->actingAs(userWithRole('registered-user', twoFactor: false))->get('/__builder-preview')->assertForbidden();
});

it('validates list blocks and resolves their item links', function () {
    $editor = userWithRole('editor');

    $this->actingAs($editor)->postJson(route('admin.api.blocks.resolve'), ['blocks' => [
        ['uuid' => '01k00000000000000000000lst', 'type' => 'list', 'content' => ['style' => 'icon', 'items' => [
            ['text' => 'Contact us', 'icon' => 'bi-envelope', 'link' => ['type' => 'url', 'url' => '/contact']],
            ['text' => 'Plain item'],
        ]]],
    ]])
        ->assertOk()
        ->assertJsonPath('blocks.0.content.items.0.link.href', '/contact')
        ->assertJsonPath('blocks.0.content.items.1.text', 'Plain item');

    $this->actingAs($editor)->postJson(route('admin.api.blocks.resolve'), ['blocks' => [
        ['uuid' => '01k00000000000000000000lsx', 'type' => 'list', 'content' => ['style' => 'stars', 'items' => [['text' => '']]]],
    ]])->assertJsonValidationErrors(['blocks.01k00000000000000000000lsx.content.style', 'blocks.01k00000000000000000000lsx.content.items.0.text']);
});

it('validates line height and list spacing options', function () {
    $editor = userWithRole('editor');

    $this->actingAs($editor)->postJson(route('admin.api.blocks.resolve'), ['blocks' => [
        ['uuid' => '01k00000000000000000000lh1', 'type' => 'list', 'content' => ['item_padding' => 'md', 'dividers' => true, 'items' => [['text' => 'A']]],
            'layout' => ['padding' => ['left' => ['$token' => 'space.4']], 'gap' => ['$token' => 'space.2']],
            'style' => ['typography' => ['line_height' => 1.8]]],
    ]])
        ->assertOk()
        ->assertJsonPath('blocks.0.style.typography.line_height', 1.8)
        ->assertJsonPath('blocks.0.content.item_padding', 'md');

    $this->actingAs($editor)->postJson(route('admin.api.blocks.resolve'), ['blocks' => [
        ['uuid' => '01k00000000000000000000lh2', 'type' => 'heading', 'content' => ['text' => 'X', 'level' => '2'], 'style' => ['typography' => ['line_height' => '1.5; color:red']]],
    ]])->assertJsonValidationErrors('blocks.01k00000000000000000000lh2.style.typography.line_height');
});
