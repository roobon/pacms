<?php

use App\Cms\Blocks\BlockPayloadResolver;
use App\Cms\Blocks\BlockTreeValidator;
use App\Models\Media;
use Illuminate\Testing\TestResponse;

/** Phase 11W: the Split block, images filling the width and the Tiles display. */
function resolveFullWidth(array $blocks): TestResponse
{
    return test()->actingAs(userWithRole('editor'))->postJson(route('admin.api.blocks.resolve'), ['blocks' => $blocks]);
}

function splitBlock(array $content = [], array $extra = []): array
{
    return ['uuid' => '01k000000000000000000split', 'type' => 'split', 'content' => $content + ['image_side' => 'right', 'image_width' => 'wide', 'height' => 'lg'],
        'children' => [['uuid' => '01k0000000000000000000head', 'type' => 'heading', 'content' => ['text' => 'Using nature as a classroom', 'level' => '2']]]] + $extra;
}

it('places a split on the page with text beside the photo, and checks its options', function () {
    $clean = app(BlockTreeValidator::class)->validate([splitBlock()], userWithRole('super-admin'));
    $block = app(BlockPayloadResolver::class)->resolve($clean)[0];

    expect($block['type'])->toBe('split')
        ->and($block['content']['image_side'])->toBe('right')
        ->and($block['content']['image_width'])->toBe('wide')
        ->and($block['children'][0]['type'])->toBe('heading');

    resolveFullWidth([splitBlock(['image_side' => 'top'])])->assertUnprocessable();
    resolveFullWidth([splitBlock(['height' => 'huge'])])->assertUnprocessable();
});

it('keeps a split at page level, like a hero', function () {
    resolveFullWidth([['uuid' => '01k0000000000000000section', 'type' => 'section', 'children' => [splitBlock()]]])
        ->assertUnprocessable();
});

it('lets an image fill the width at a banner height', function () {
    $editor = userWithRole('editor');
    $this->actingAs($editor)->post(route('admin.media.store'), ['files' => [fakeJpeg()]]);
    $image = fn (array $content) => [['uuid' => '01k000000000000000000image', 'type' => 'image', 'content' => ['image' => ['$media' => Media::firstOrFail()->id]] + $content]];
    $resolve = fn (array $content) => $this->actingAs($editor)->postJson(route('admin.api.blocks.resolve'), ['blocks' => $image($content)]);

    $resolve(['size' => 'fill', 'height' => 'md'])
        ->assertOk()
        ->assertJsonPath('blocks.0.content.size', 'fill')
        ->assertJsonPath('blocks.0.content.height', 'md');

    $resolve(['size' => 'giant'])->assertUnprocessable();
    $resolve(['height' => '120vh'])->assertUnprocessable();
});

it('offers tiles for galleries, news and module collections only', function () {
    $tiles = ['mode' => 'tiles', 'columns' => ['desktop' => 5, 'mobile' => 2], 'image_ratio' => '1:1'];

    resolveFullWidth([['uuid' => '01k0000000000000000000news', 'type' => 'news', 'source' => ['mode' => 'dynamic', 'entity' => 'news', 'order' => 'latest', 'limit' => 5], 'display' => $tiles]])
        ->assertOk()
        ->assertJsonPath('blocks.0.display.mode', 'tiles')
        ->assertJsonPath('blocks.0.display.columns.desktop', 5);
    resolveFullWidth([['uuid' => '01k000000000000000projects', 'type' => 'projects', 'source' => ['mode' => 'dynamic', 'entity' => 'projects', 'order' => 'latest', 'limit' => 4], 'display' => $tiles]])
        ->assertOk();
    resolveFullWidth([['uuid' => '01k00000000000000000gallery', 'type' => 'gallery', 'content' => ['columns' => '6'], 'display' => ['mode' => 'tiles']]])
        ->assertOk()
        ->assertJsonPath('blocks.0.content.columns', '6');

    // Cards keep their own modes.
    resolveFullWidth([['uuid' => '01k0000000000000000000card', 'type' => 'cards', 'content' => ['items' => [['title' => 'One']]], 'display' => $tiles]])
        ->assertUnprocessable();
});
