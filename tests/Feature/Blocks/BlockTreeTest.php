<?php

use App\Cms\Blocks\BlockRegistry;
use App\Cms\Blocks\BlockTreeRepository;
use App\Enums\WorkflowAction;
use App\Models\Block;
use App\Models\BlockType;
use App\Models\ContentReference;
use App\Models\Media;
use App\Services\Pages\PageService;
use App\Services\Publishing\PublishingService;

/**
 * Save blocks through the real page form (the builder writes JSON into "blocks").
 *
 * @param  list<array<string, mixed>>  $blocks
 */
function saveBlocks($test, $user, $page, array $blocks)
{
    return $test->actingAs($user)->put(route('admin.pages.update', $page), pagePayload([
        'title' => $page->title,
        'slug' => $page->slug,
        'lock_version' => $page->fresh()->lock_version,
        'blocks' => json_encode($blocks),
    ]));
}

function heroTree(): array
{
    return [[
        'uuid' => '01k0000000000000000000hero',
        'type' => 'hero',
        'layout' => ['container' => 'boxed', 'min_height' => ['value' => 60, 'unit' => 'vh']],
        'style' => ['background' => ['type' => 'color', 'color' => ['$token' => 'color.bg-dark']]],
        'children' => [
            ['uuid' => '01k00000000000000000000h1x', 'type' => 'heading', 'content' => ['text' => 'Welcome', 'level' => '1']],
            ['uuid' => '01k00000000000000000000rtx', 'type' => 'rich-text', 'content' => ['html' => '<p>Hello <strong>world</strong></p>']],
        ],
    ], [
        'uuid' => '01k000000000000000000sectn',
        'type' => 'section',
        'children' => [[
            'type' => 'columns',
            'layout' => ['columns' => ['desktop' => [6, 6], 'mobile' => [12, 12]]],
            'children' => [
                ['type' => 'column', 'children' => [['type' => 'heading', 'content' => ['text' => 'Left', 'level' => '2']]]],
                ['type' => 'column', 'children' => [['type' => 'button', 'content' => ['label' => 'Go', 'link' => ['type' => 'url', 'url' => '/contact']]]]],
            ],
        ]],
    ]];
}

it('synchronises every core block type', function () {
    expect(BlockType::count())->toBe(count(config('pacms.blocks.types')))
        ->and(BlockType::where('slug', 'hero')->value('category'))->toBe('content');

    $result = app(BlockRegistry::class)->sync();
    expect($result['created'])->toBe(0);
});

it('saves and reloads a nested tree in order with stable uuids', function () {
    $editor = userWithRole('editor');
    $page = makePage($editor, ['title' => 'Home']);

    saveBlocks($this, $editor, $page, heroTree())->assertSessionHasNoErrors();

    $tree = app(BlockTreeRepository::class)->load($page->fresh());
    expect($tree)->toHaveCount(2)
        ->and($tree[0]['uuid'])->toBe('01k0000000000000000000hero')
        ->and($tree[0]['children'][0]['content']['text'])->toBe('Welcome')
        ->and($tree[1]['children'][0]['type'])->toBe('columns')
        ->and($tree[1]['children'][0]['children'][1]['children'][0]['type'])->toBe('button')
        ->and(Block::count())->toBe(9);
});

it('rejects structures the block rules do not allow', function (array $blocks, string $message) {
    $editor = userWithRole('editor');
    $page = makePage($editor);

    $response = saveBlocks($this, $editor, $page, $blocks)->assertSessionHasErrors();
    expect(collect(session('errors')->getMessages())->flatten()->implode(' '))->toContain($message);
    expect(Block::count())->toBe(0);
})->with([
    'unknown type' => [[['type' => 'iframe-embed']], 'Unknown block type'],
    'column at page level' => [[['type' => 'column']], 'cannot be placed at page level'],
    'section in section' => [[['type' => 'section', 'children' => [['type' => 'section']]]], 'cannot be placed inside'],
    'children in a heading' => [[['type' => 'heading', 'content' => ['text' => 'x'], 'children' => [['type' => 'heading', 'content' => ['text' => 'y']]]]], 'cannot contain other blocks'],
    'missing required field' => [[['type' => 'heading', 'content' => ['text' => '']]], 'Heading is required'],
]);

it('enforces depth and size limits', function () {
    config(['pacms.blocks.max_depth' => 2, 'pacms.blocks.max_nodes' => 3]);
    $editor = userWithRole('editor');
    $page = makePage($editor);

    saveBlocks($this, $editor, $page, [['type' => 'container', 'children' => [['type' => 'container', 'children' => [['type' => 'spacer']]]]]])
        ->assertSessionHasErrors('blocks');

    saveBlocks($this, $editor, $page, array_fill(0, 4, ['type' => 'spacer']))->assertSessionHasErrors('blocks');
});

it('only accepts design tokens and validated values for styles (no CSS injection)', function (array $node) {
    $editor = userWithRole('editor');
    $page = makePage($editor);

    saveBlocks($this, $editor, $page, [$node])->assertSessionHasErrors();
})->with([
    'unknown token' => [['type' => 'container', 'layout' => ['gap' => ['$token' => 'space.evil']]]],
    'colour token used as spacing' => [['type' => 'container', 'layout' => ['padding' => ['top' => ['$token' => 'color.primary']]]]],
    'css in colour' => [['type' => 'container', 'style' => ['background' => ['type' => 'color', 'color' => 'red;}body{display:none']]]],
    'bad unit' => [['type' => 'container', 'layout' => ['min_height' => ['value' => 10, 'unit' => 'px;}']]]],
    'invalid anchor' => [['type' => 'container', 'advanced' => ['anchor' => '"><script>']]],
    'hidden everywhere' => [['type' => 'container', 'advanced' => ['visibility' => ['hide_on' => ['desktop', 'tablet', 'mobile']]]]],
]);

it('rejects unsafe links and strips scripts from rich text', function () {
    $editor = userWithRole('editor');
    $page = makePage($editor);

    saveBlocks($this, $editor, $page, [['type' => 'button', 'content' => ['label' => 'x', 'link' => ['type' => 'url', 'url' => 'javascript:alert(1)']]]])
        ->assertSessionHasErrors();

    saveBlocks($this, $editor, $page, [['type' => 'rich-text', 'content' => ['html' => '<p onclick="x()">Hi<script>alert(1)</script><a href="javascript:x()">l</a><img src=x onerror=y></p>']]])
        ->assertSessionHasNoErrors();

    $html = app(BlockTreeRepository::class)->load($page->fresh())[0]['content']['html'];
    expect($html)->toContain('Hi')
        ->not->toContain('script')
        ->not->toContain('onclick')
        ->not->toContain('javascript:')
        ->not->toContain('onerror');
});

it('allows only whitelisted custom attributes, and only with permission', function () {
    $page = makePage(userWithRole('editor'));
    $node = ['type' => 'container', 'advanced' => ['attributes' => ['data-track' => 'cta']]];

    saveBlocks($this, userWithRole('editor'), $page, [$node])->assertSessionHasErrors();

    $admin = userWithRole('administrator');
    saveBlocks($this, $admin, $page, [$node])->assertSessionHasNoErrors();
    saveBlocks($this, $admin, $page, [['type' => 'container', 'advanced' => ['attributes' => ['onclick' => 'alert(1)']]]])->assertSessionHasErrors();
    saveBlocks($this, $admin, $page, [['type' => 'container', 'advanced' => ['attributes' => ['style' => 'x']]]])->assertSessionHasErrors();
});

it('tracks media used in blocks and blocks deleting it', function () {
    $editor = userWithRole('editor');
    $this->actingAs($editor)->post(route('admin.media.store'), ['files' => [fakeJpeg()]]);
    $media = Media::firstOrFail();
    $page = makePage($editor);

    saveBlocks($this, $editor, $page, [['type' => 'image', 'content' => ['image' => ['$media' => $media->id]]]])->assertSessionHasNoErrors();

    expect(ContentReference::where('target_id', $media->id)->where('context', 'block_content')->exists())->toBeTrue();
    $this->actingAs($editor)->delete(route('admin.media.destroy', $media))->assertSessionHasErrors('media');
});

it('refuses private files and missing media in public blocks', function () {
    $editor = userWithRole('editor');
    $this->actingAs($editor)->post(route('admin.media.store'), ['files' => [fakeJpeg()], 'private' => 1]);
    $private = Media::firstOrFail();
    $page = makePage($editor);

    saveBlocks($this, $editor, $page, [['type' => 'image', 'content' => ['image' => ['$media' => $private->id]]]])->assertSessionHasErrors();
    saveBlocks($this, $editor, $page, [['type' => 'image', 'content' => ['image' => ['$media' => 99999]]]])->assertSessionHasErrors();
});

it('publishes blocks as a snapshot and resolves them for visitors', function () {
    $editor = userWithRole('editor');
    $this->actingAs($editor)->post(route('admin.media.store'), ['files' => [fakeJpeg('hero.jpg', 1600, 900)]]);
    $media = Media::firstOrFail();
    $contact = livePage(['title' => 'Contact']);
    $page = makePage($editor, ['title' => 'Landing']);

    saveBlocks($this, $editor, $page, [
        ['type' => 'heading', 'content' => ['text' => 'Visible', 'level' => '1']],
        ['type' => 'heading', 'hidden' => true, 'content' => ['text' => 'Secret draft heading', 'level' => '2']],
        ['type' => 'image', 'content' => ['image' => ['$media' => $media->id], 'alt' => 'A forest']],
        ['type' => 'button', 'content' => ['label' => 'Contact us', 'link' => ['type' => 'entity', 'entity' => 'pages', 'id' => $contact->id]]],
    ])->assertSessionHasNoErrors();

    app(PublishingService::class)->transition($page->fresh(), WorkflowAction::Publish, $editor);

    $blocks = $this->getJson('/api/v1/pages/landing')->assertOk()->json('data.blocks');
    expect($blocks)->toHaveCount(3)
        ->and(json_encode($blocks))->not->toContain('Secret draft heading')
        ->and($blocks[1]['content']['image']['srcset'])->toContain('640w')
        ->and($blocks[1]['content']['alt'])->toBe('A forest')
        ->and($blocks[2]['content']['link'])->toBe(['href' => '/contact', 'new_tab' => false, 'external' => false]);

    // Editing the working copy does not change the live page until publish.
    app(PageService::class)->update($editor, $page->fresh(), ['blocks' => [['type' => 'heading', 'content' => ['text' => 'Draft only', 'level' => '1']]]], $page->fresh()->lock_version);
    $this->getJson('/api/v1/pages/landing')->assertJsonPath('data.blocks.0.content.text', 'Visible');
});

it('restores the block tree from a revision', function () {
    $editor = userWithRole('editor');
    $page = makePage($editor);
    saveBlocks($this, $editor, $page, [['type' => 'heading', 'content' => ['text' => 'Version A', 'level' => '2']]]);
    $revisionA = $page->revisions()->first();
    saveBlocks($this, $editor, $page, [['type' => 'heading', 'content' => ['text' => 'Version B', 'level' => '2']]]);

    $this->actingAs($editor)->post(route('admin.pages.revisions.restore', [$page, $revisionA]))->assertRedirect();

    expect(app(BlockTreeRepository::class)->load($page->fresh())[0]['content']['text'])->toBe('Version A');
});

it('shows the builder on the page edit screen with the current tree', function () {
    $editor = userWithRole('editor');
    $page = makePage($editor);
    saveBlocks($this, $editor, $page, heroTree());

    $this->actingAs($editor)->get(route('admin.pages.edit', $page))
        ->assertOk()
        ->assertSee('id="page-builder"', false)
        ->assertSee('id="blocks-input"', false)
        ->assertSee('01k0000000000000000000hero');
});
