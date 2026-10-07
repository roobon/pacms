<?php

use App\Cms\Blocks\BlockTreeRepository;
use App\Models\BlockTemplate;
use App\Models\Media;
use App\Models\Page;
use App\Models\Term;
use App\Services\Blocks\BlockTemplateService;
use App\Services\Blocks\GlobalBlockService;
use App\Services\Media\MediaService;

/**
 * The tree without uuids, for comparing an original with its re-imported copy.
 *
 * @param  list<array<string, mixed>>  $nodes
 * @return list<array<string, mixed>>
 */
function withoutUuids(array $nodes): array
{
    return array_map(function ($node) {
        unset($node['uuid']);
        if (! empty($node['children'])) {
            $node['children'] = withoutUuids($node['children']);
        }

        return $node;
    }, $nodes);
}

it('round-trips a page: export then import produces an equivalent tree', function () {
    $editor = userWithRole('editor');
    $media = app(MediaService::class)->store(fakeJpeg(), $editor, ['alt' => 'Forest']);
    $about = livePage(['title' => 'About']);
    $category = Term::query()->create(['taxonomy' => 'news_category', 'name' => 'Environment', 'slug' => 'environment']);
    $globals = app(GlobalBlockService::class);
    $global = $globals->publish($editor, $globals->create($editor, ['name' => 'CTA', 'blocks' => [['type' => 'divider']]]));

    $page = livePage(['title' => 'Source']);
    $this->actingAs($editor)->put(route('admin.pages.update', $page), pagePayload([
        'title' => 'Source', 'slug' => 'source', 'lock_version' => $page->lock_version, 'featured_media_id' => $media->id,
        'blocks' => json_encode([
            ['type' => 'section', 'layout' => ['container' => 'narrow'], 'style' => ['background' => ['type' => 'image', 'image' => ['$media' => $media->id], 'position' => 'center', 'size' => 'cover']], 'children' => [
                ['type' => 'heading', 'content' => ['text' => 'Hello', 'level' => '1']],
                ['type' => 'image', 'content' => ['image' => ['$media' => $media->id], 'alt' => 'Forest']],
                ['type' => 'button', 'content' => ['label' => 'About', 'link' => ['type' => 'entity', 'entity' => 'pages', 'id' => $about->id]]],
                ['type' => 'news', 'source' => ['mode' => 'dynamic', 'entity' => 'news', 'filters' => ['category' => $category->id], 'limit' => 3], 'display' => ['mode' => 'grid']],
            ]],
            ['type' => 'global-ref', 'global_block_id' => $global->id],
        ]),
    ]))->assertSessionHasNoErrors();
    $original = app(BlockTreeRepository::class)->load($page->fresh());

    $export = $this->actingAs($editor)->get(route('admin.export.page', $page))->assertOk()->json();

    expect($export['schema_version'])->toBe('1.0')
        ->and($export['kind'])->toBe('page')
        ->and($export['assets'][0])->toMatchArray(['key' => "media-{$media->id}", 'media' => $media->id, 'strategy' => 'existing', 'alt' => 'Forest'])
        ->and($export['assets'][0]['url'])->toStartWith('http')
        ->and($export['page']['blocks'][0]['children'][2]['content']['link']['ref']['$ref'])->toBe(['entity' => 'pages', 'path' => 'about'])
        ->and($export['page']['blocks'][0]['children'][3]['source']['filters']['category']['$ref']['slug'])->toBe('environment')
        ->and($export['page']['blocks'][1]['content']['global']['$ref']['slug'])->toBe($global->slug)
        ->and(json_encode($export))->not->toContain($editor->email);

    $job = analyse($editor, $export);
    expect(messages($job, 'error'))->toBe([]);
    $this->actingAs($editor)->post(route('admin.import.confirm', $job), ['target' => 'new', 'acknowledge' => '1']);

    $copy = Page::query()->findOrFail($job->fresh()->result_id);
    expect($copy->slug)->toBe('source-2')
        ->and($copy->featured_media_id)->toBe($media->id)
        ->and(withoutUuids(app(BlockTreeRepository::class)->load($copy)))->toBe(withoutUuids($original))
        ->and(Media::count())->toBe(1); // existing media reused, nothing re-downloaded
});

it('exports templates and builder selections, with permissions', function () {
    $editor = userWithRole('editor');
    $template = app(BlockTemplateService::class)->create($editor, ['name' => 'Divider set', 'scope' => 'block', 'blocks' => [['type' => 'divider']]]);

    $this->actingAs($editor)->get(route('admin.export.template', $template))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="template-divider-set.pacms.json"')
        ->assertJsonPath('kind', 'template')
        ->assertJsonPath('template.name', 'Divider set');

    $this->actingAs($editor)->postJson(route('admin.api.export.blocks'), ['title' => 'Intro', 'blocks' => [
        ['type' => 'section', 'children' => [['type' => 'heading', 'content' => ['text' => 'Hi', 'level' => '2']]]],
    ]])->assertOk()->assertJsonPath('kind', 'section')->assertJsonPath('blocks.0.children.0.content.text', 'Hi');

    $this->actingAs(userWithRole('moderator', twoFactor: false))->get(route('admin.export.template', BlockTemplate::first()))->assertForbidden();
});

it('publishes a JSON Schema and AI instructions generated from the block types', function () {
    $admin = userWithRole('administrator');

    $schema = $this->actingAs($admin)->get(route('admin.import.schema'))->assertOk()->json();
    expect($schema['$schema'])->toBe('https://json-schema.org/draft/2020-12/schema')
        ->and($schema['$defs']['node']['properties']['type']['enum'])->toContain('heading', 'list', 'section')
        ->and($schema['$defs']['content__heading']['required'])->toBe(['text'])
        ->and($schema['$defs']['content__heading']['properties']['level']['enum'])->toContain('1', '6');

    $this->actingAs($admin)->get(route('admin.import.index'))
        ->assertOk()
        ->assertSee('Block types available on this site', false)
        ->assertSee('- heading (Heading)', false);
});
