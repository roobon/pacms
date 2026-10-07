<?php

use App\Cms\Blocks\BlockTreeRepository;
use App\Models\BlockTemplate;
use App\Models\ImportJob;
use App\Models\Media;
use App\Models\Page;
use App\Services\Media\MediaService;
use App\Support\Http\SafeHttpClient;

it('accepts every complete example in CMS-BLOCK-SCHEMA.md without errors', function () {
    $editor = userWithRole('editor');

    $doc = (string) file_get_contents(base_path('docs/CMS-BLOCK-SCHEMA.md'));
    $examples = substr($doc, (int) strpos($doc, '## 20. Complete examples'));
    preg_match_all('/```json\n(.*?)\n```/s', $examples, $matches);

    expect($matches[1])->toHaveCount(5);
    foreach ($matches[1] as $i => $json) {
        $job = analyse($editor, $json);
        expect(messages($job, 'error'))->toBe([], 'Example '.($i + 1).' has errors')
            ->and($job->status)->toBe(ImportJob::AWAITING);
    }
});

it('imports an AI-written page as a draft with downloaded images', function () {
    fakeDownloads();
    $editor = userWithRole('editor');

    $job = analyse($editor, [
        'schema_version' => '1.0',
        'kind' => 'page',
        'meta' => ['generator' => 'Claude', 'title' => 'About us'],
        'assets' => [['key' => 'team', 'url' => 'https://images.example.org/team.jpg', 'alt' => 'Our team', 'strategy' => 'download']],
        'page' => [
            'title' => 'About us', 'slug' => 'about-us', 'excerpt' => 'Who we are.',
            'featured_image' => ['$asset' => 'team'],
            'seo' => ['description' => 'About our foundation.'],
            'blocks' => [['type' => 'section', 'key' => 'intro', 'children' => [
                ['type' => 'heading', 'content' => ['text' => 'About us', 'level' => 1]],
                ['type' => 'image', 'content' => ['image' => ['$asset' => 'team'], 'alt' => 'Our team']],
                ['type' => 'button', 'content' => ['label' => 'Contact', 'link' => ['type' => 'url', 'url' => '/contact']]],
            ]]],
        ],
    ]);
    expect(messages($job, 'error'))->toBe([]);

    $this->actingAs($editor)->post(route('admin.import.confirm', $job), ['target' => 'new'])->assertRedirect(route('admin.import.show', $job));

    $job->refresh();
    $page = Page::query()->where('slug', 'about-us')->firstOrFail();
    $tree = app(BlockTreeRepository::class)->load($page);

    expect($job->status)->toBe(ImportJob::COMPLETED)
        ->and($job->result_id)->toBe($page->id)
        ->and($page->isLive())->toBeFalse()                       // drafts only
        ->and($page->featured_media_id)->not->toBeNull()
        ->and(Media::query()->find($page->featured_media_id)?->alt)->toBe('Our team')
        ->and($tree[0]['children'][0]['content']['level'])->toBe('1')  // 1 → "1"
        ->and($tree[0]['children'][1]['content']['image'])->toBe(['$media' => $page->featured_media_id])
        ->and($page->seo?->description)->toBe('About our foundation.');

    // Confirming again does nothing.
    $this->actingAs($editor)->post(route('admin.import.confirm', $job), ['target' => 'new'])->assertStatus(409);
    expect(Page::query()->where('title', 'About us')->count())->toBe(1);
});

it('strips or rejects malicious and unsupported content and reports it', function () {
    $editor = userWithRole('editor');

    $job = analyse($editor, [
        'schema_version' => '1.0',
        'kind' => 'section',
        'run' => 'rm -rf /',
        'blocks' => [[
            'type' => 'section',
            'advanced' => ['custom_css' => 'body{display:none}', 'attributes' => ['onclick' => 'alert(1)']],
            'children' => [
                ['type' => 'rich-text', 'key' => 'evil', 'content' => ['html' => '<p onclick="x()">Hi<script>alert(1)</script><a href="javascript:alert(1)">x</a></p>']],
                ['type' => 'heading', 'content' => ['text' => ['$bind' => 'name'], 'level' => '2']],
                ['type' => 'button', 'content' => ['label' => 'Go', 'link' => ['type' => 'url', 'url' => 'javascript:alert(1)']]],
                ['type' => 'image', 'content' => ['image' => ['$media' => 999999]]],
                ['type' => 'php-eval', 'content' => ['code' => '<?php system("id");']],
            ],
        ]],
    ]);

    $warnings = implode("\n", messages($job, 'warning'));
    $errors = messages($job, 'error');
    $blocks = $job->documentData()['blocks'];

    expect($warnings)->toContain('Unsupported property "run"')
        ->toContain('Custom CSS is not accepted')
        ->toContain('Block type "php-eval" is not available')
        ->toContain('The HTML was cleaned')
        ->and(json_encode($blocks))->not->toContain('script')->not->toContain('onclick')->not->toContain('javascript:')->not->toContain('$bind')
        // Required values that are missing or unsafe stop the import (heading text, button link, image).
        ->and($errors)->not->toBe([])
        ->and($job->status)->toBe(ImportJob::FAILED);

    $this->actingAs($editor)->post(route('admin.import.confirm', $job), ['target' => 'template'])->assertStatus(409);
});

it('refuses broken documents with a clear error', function (string $json, string $expected) {
    $job = analyse(userWithRole('editor'), $json);

    expect($job->status)->toBe(ImportJob::FAILED)
        ->and(implode(' ', messages($job, 'error')))->toContain($expected);
})->with([
    'not json' => ['{"schema_version": "1.0", ', 'not valid JSON'],
    'no version' => ['{"kind": "block", "blocks": [{"type": "divider"}]}', 'schema_version'],
    'future major' => ['{"schema_version": "2.0", "kind": "block", "blocks": [{"type": "divider"}]}', 'not supported'],
    'site packages' => ['{"schema_version": "1.0", "kind": "site"}', 'not supported'],
    'too deep' => [json_encode(['schema_version' => '1.0', 'kind' => 'block', 'blocks' => [array_reduce(range(1, 13), fn ($child) => ['type' => 'container', 'children' => $child ? [$child] : []])]]), 'nested'],
    'page without title' => ['{"schema_version": "1.0", "kind": "page", "page": {"blocks": []}}', 'title'],
]);

it('accepts a Markdown code fence around the JSON (common AI output)', function () {
    $job = analyse(userWithRole('editor'), "```json\n".json_encode(['schema_version' => '1.0', 'kind' => 'block', 'blocks' => [['type' => 'divider']]])."\n```");

    expect($job->status)->toBe(ImportJob::AWAITING);
});

it('leaves images empty when a download is unsafe or fails, never substituting', function () {
    fakeDownloads();
    app()->instance(SafeHttpClient::class, new SafeHttpClient(fn ($host) => $host === 'internal.example.org' ? ['10.0.0.8'] : ['93.184.216.34']));
    $editor = userWithRole('editor');
    $page = livePage(['title' => 'Target']);

    $job = analyse($editor, [
        'schema_version' => '1.0', 'kind' => 'block',
        'assets' => [['key' => 'bad', 'url' => 'http://internal.example.org/secret.png', 'strategy' => 'download']],
        'blocks' => [['type' => 'section', 'style' => ['background' => ['type' => 'image', 'image' => ['$asset' => 'bad']]], 'children' => [
            ['type' => 'heading', 'content' => ['text' => 'Kept', 'level' => '2']],
            ['type' => 'image', 'content' => ['image' => ['$asset' => 'bad']]],
        ]]],
    ]);
    expect(messages($job, 'error'))->toBe([]);

    $this->actingAs($editor)->post(route('admin.import.confirm', $job), ['target' => 'page', 'page_id' => $page->id]);

    $job->refresh();
    $tree = app(BlockTreeRepository::class)->load($page);
    expect($job->status)->toBe(ImportJob::COMPLETED)
        ->and(implode(' ', messages($job, 'warning')))->toContain('could not be imported')->toContain('Block removed')
        ->and(Media::count())->toBe(0)
        ->and($tree)->toHaveCount(1)
        ->and(array_column($tree[0]['children'], 'type'))->toBe(['heading'])
        ->and($tree[0]['style']['background'] ?? [])->not->toHaveKey('image')
        // Added to the working copy only: the live page is unchanged until someone publishes.
        ->and($page->fresh()->has_unpublished_changes)->toBeTrue();
});

it('imports templates hidden from the builder and needs the right permissions', function () {
    $editor = userWithRole('editor');
    $job = analyse($editor, ['schema_version' => '1.0', 'kind' => 'template', 'template' => ['name' => 'Imported hero', 'scope' => 'section'], 'blocks' => [['type' => 'divider']]]);
    $this->actingAs($editor)->post(route('admin.import.confirm', $job), ['target' => 'template']);

    expect(BlockTemplate::query()->where('name', 'Imported hero')->value('status'))->toBe('draft');

    // Authors cannot import; other editors cannot open someone else's import.
    $this->actingAs(userWithRole('author', twoFactor: false))->get(route('admin.import.index'))->assertForbidden();
    $this->actingAs(userWithRole('editor'))->get(route('admin.import.show', $job))->assertForbidden();
    $this->actingAs($editor)->get(route('admin.import.show', $job))->assertOk()->assertSee('Imported as a draft');
});

it('repairs common AI envelope mistakes and says so', function (array $document, string $kind, string $note) {
    $job = analyse(userWithRole('editor'), ['schema_version' => '1.0'] + $document);

    expect($job->status)->toBe(ImportJob::AWAITING)
        ->and($job->kind)->toBe($kind)
        ->and(implode(' ', messages($job, 'info')))->toContain($note);
})->with([
    'page kind, blocks at the top, no title' => [['kind' => 'page', 'blocks' => [['type' => 'section', 'children' => [['type' => 'divider']]]]], 'section', 'imported as section'],
    'page kind, page fields at the top' => [['kind' => 'page', 'title' => 'Mission', 'blocks' => [['type' => 'divider']]], 'page', 'read as the page'],
    'kind missing' => [['blocks' => [['type' => 'divider']]], 'block', '"kind" was missing'],
    'made-up kind' => [['kind' => 'component', 'blocks' => [['type' => 'divider']]], 'block', '"kind": "component"'],
    'single block object' => [['kind' => 'block', 'blocks' => ['type' => 'divider']], 'block', 'list of one'],
]);

it('shows the submitted JSON so a failed document can be corrected and checked again', function () {
    $editor = userWithRole('editor');
    $job = analyse($editor, '{"schema_version": "1.0", "kind": "page", "page": {"blocks": []}}');

    $this->actingAs($editor)->get(route('admin.import.show', $job))
        ->assertOk()
        ->assertSee('Check again')
        ->assertSee('fix the error below')
        ->assertSee('&quot;kind&quot;: &quot;page&quot;', false);
});

it('reads the ChatGPT shape with blocks under "content" and a status', function () {
    $job = analyse(userWithRole('editor'), [
        'schema_version' => '1.0',
        'kind' => 'page',
        'title' => 'About Us',
        'slug' => 'about-us',
        'status' => 'draft',
        'content' => ['blocks' => [
            ['type' => 'rich-text', 'content' => ['html' => '<p><a href="/">Probha Aurora Foundation</a> / About Us</p><h1>About Us</h1><p>Building a greener future.</p>']],
            ['type' => 'heading', 'content' => ['text' => 'Our mission', 'level' => 2]],
        ]],
    ]);

    $info = implode(' ', messages($job, 'info'));
    expect($job->status)->toBe(ImportJob::AWAITING)
        ->and($job->kind)->toBe('page')
        ->and($job->documentData()['page']['title'])->toBe('About Us')
        ->and($job->documentData()['blocks'])->toHaveCount(2)
        ->and($info)->toContain('under "content/blocks"')->toContain('"status" is ignored')->toContain('read as the page')
        ->and(implode(' ', messages($job, 'warning')))->toContain('The HTML was cleaned'); // <h1> is not allowed in rich text
});

it('previews imports with library images and pending downloads without errors', function () {
    $editor = userWithRole('editor');
    $media = app(MediaService::class)->store(fakeJpeg(), $editor, ['alt' => 'Library photo']);

    $job = analyse($editor, [
        'schema_version' => '1.0', 'kind' => 'block',
        'assets' => [
            ['key' => 'lib', 'media' => $media->id, 'strategy' => 'existing'],
            ['key' => 'new', 'url' => 'https://images.example.org/new.jpg', 'strategy' => 'download'],
        ],
        'blocks' => [
            ['type' => 'image', 'content' => ['image' => ['$asset' => 'lib'], 'alt' => 'A']],
            ['type' => 'image', 'content' => ['image' => ['$asset' => 'new'], 'alt' => 'B']],
        ],
    ]);

    $page = $this->actingAs($editor)->get(route('admin.import.show', $job))->assertOk();
    $data = json_decode(html_entity_decode((string) str($page->getContent())->between('id="page-builder-data" type="application/json">', '</script>')), true);

    expect($data['blocks'][0]['content']['image'])->toBe(['$media' => $media->id])
        ->and($data['pendingAssets'])->toBe(['new']);

    // The preview endpoint accepts the pending image as a placeholder.
    $this->actingAs($editor)->postJson(route('admin.api.blocks.resolve'), ['blocks' => $data['blocks'], 'assets' => ['new']])
        ->assertOk()
        ->assertJsonPath('blocks.0.content.image.id', $media->id)
        ->assertJsonPath('blocks.1.content.image', null);
});
