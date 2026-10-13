<?php

use App\Cms\Blocks\BlockPayloadResolver;
use App\Cms\Blocks\BlockTreeValidator;
use Illuminate\Testing\TestResponse;

/** Phase 11I: image shapes, the Logo row, letter spacing and the stacked site logo. */
function resolveTree(array $blocks): TestResponse
{
    return test()->actingAs(userWithRole('editor'))->postJson(route('admin.api.blocks.resolve'), ['blocks' => $blocks]);
}

it('accepts the new options and refuses values outside their lists', function () {
    resolveTree([
        ['uuid' => '01k0000000000000000000logo', 'type' => 'site-logo', 'content' => ['layout' => 'stacked']],
        ['uuid' => '01k000000000000000000title', 'type' => 'heading', 'content' => ['text' => 'Our programmes', 'level' => '2'],
            'style' => ['typography' => ['transform' => 'uppercase', 'letter_spacing' => 'wide']]],
    ])->assertOk()
        ->assertJsonPath('blocks.0.content.layout', 'stacked')
        ->assertJsonPath('blocks.1.style.typography.letter_spacing', 'wide');

    resolveTree([['uuid' => '01k000000000000000000title', 'type' => 'heading', 'content' => ['text' => 'X', 'level' => '2'], 'style' => ['typography' => ['letter_spacing' => '5px']]]])
        ->assertUnprocessable();
    resolveTree([['uuid' => '01k000000000000000000logo2', 'type' => 'site-logo', 'content' => ['layout' => 'sideways']]])
        ->assertUnprocessable();
});

it('validates the image shape', function () {
    $errors = resolveTree([['uuid' => '01k000000000000000000image', 'type' => 'image', 'content' => ['shape' => 'star']]])
        ->assertUnprocessable()->json('errors');

    expect(array_keys($errors))->toContain('blocks.01k000000000000000000image.content.shape');
});

it('shows a row of named logos with links, a logo being optional', function () {
    $clean = app(BlockTreeValidator::class)->validate([[
        'type' => 'logos',
        'content' => ['size' => 'lg', 'show_labels' => true, 'items' => [
            ['label' => 'Eco-Schools', 'link' => ['type' => 'url', 'url' => '/programs']],
            ['label' => 'Young Reporters'],
        ]],
    ]], userWithRole('super-admin'));
    $block = app(BlockPayloadResolver::class)->resolve($clean)[0];

    expect($block['type'])->toBe('logos')
        ->and(array_column($block['content']['items'], 'label'))->toBe(['Eco-Schools', 'Young Reporters'])
        ->and($block['content']['items'][0]['link']['href'])->toBe('/programs');

    resolveTree([['uuid' => '01k0000000000000000000logs', 'type' => 'logos', 'content' => ['items' => [['link' => ['type' => 'url', 'url' => '/x']]]]]])
        ->assertUnprocessable();
});
