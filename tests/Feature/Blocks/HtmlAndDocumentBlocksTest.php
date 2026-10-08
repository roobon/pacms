<?php

use App\Cms\Blocks\BlockPayloadResolver;
use App\Cms\Blocks\BlockTreeRepository;
use App\Cms\Blocks\BlockTreeValidator;
use App\Models\BlockTemplate;
use App\Models\Media;
use App\Services\Media\MediaService;
use App\Services\Pages\PageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake((string) config('pacms.media.disk'));
    Storage::fake((string) config('pacms.media.private_disk'));
});

function storedPdf(string $name = 'guide.pdf', bool $private = false): Media
{
    $file = UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n% ".uniqid()."\ntrailer<<>>\n%%EOF");

    return app(MediaService::class)->store($file, userWithRole('super-admin'), [], $private);
}

it('cleans HTML blocks: keeps layout, classes and safe styles, removes code', function () {
    $html = '<div class="row g-3" style="color: #064e3b; padding: 1rem" onclick="steal()">'
        .'<table class="table"><tr><td colspan="2">Cell</td></tr></table>'
        .'<p style="background: url(https://evil.example/x.png)">Tracked</p>'
        .'<script>alert(1)</script><style>body{display:none}</style>'
        .'<iframe src="https://evil.example"></iframe><a href="javascript:alert(1)">bad</a>'
        .'<img src="/storage/media/a.webp" alt="A" onerror="x()"></div>';

    $clean = app(BlockTreeValidator::class)->validate([['type' => 'html', 'content' => ['html' => $html]]], userWithRole('administrator'))[0]['content'];

    expect($clean['html'])
        ->toContain('class="row g-3"')->toContain('padding: 1rem')->toContain('<td colspan="2">Cell</td>')->toContain('alt="A"')
        ->not->toContain('onclick')->not->toContain('script')->not->toContain('<style')->not->toContain('iframe')
        ->not->toContain('javascript:')->not->toContain('onerror')->not->toContain('url(')
        ->and($clean['signature'])->toBe(BlockTreeValidator::htmlSignature($clean['html']));
});

it('lets only permitted roles add or change HTML blocks, while others keep them', function () {
    $admin = userWithRole('administrator');
    $editor = userWithRole('editor');
    $validator = app(BlockTreeValidator::class);

    expect($admin->can('blocks.custom_html'))->toBeTrue()->and($editor->can('blocks.custom_html'))->toBeFalse();

    // An editor cannot add one…
    expect(fn () => $validator->validate([['type' => 'html', 'content' => ['html' => '<p>Mine</p>']]], $editor))
        ->toThrow(ValidationException::class);

    // …but can save a page that contains one an administrator added, unchanged or moved.
    $page = makePage($admin);
    app(PageService::class)->update($admin, $page, ['blocks' => [['type' => 'html', 'content' => ['html' => '<p>Admin markup</p>']]]], $page->lock_version);
    $saved = app(BlockTreeRepository::class)->load($page->fresh());
    $kept = $validator->validate([['type' => 'heading', 'content' => ['text' => 'Intro', 'level' => '2']], ...$saved], $editor);
    expect($kept[1]['content']['html'])->toBe('<p>Admin markup</p>');

    // …and cannot change it.
    $saved[0]['content']['html'] = '<p>Changed by editor</p>';
    expect(fn () => $validator->validate($saved, $editor))->toThrow(ValidationException::class);

    $this->actingAs($editor)->getJson(route('admin.api.blocks.definitions'))->assertJsonPath('permissions.custom_html', false);
    $this->actingAs($admin)->getJson(route('admin.api.blocks.definitions'))->assertJsonPath('permissions.custom_html', true);
});

it('shows a document block with its public file, and refuses private or non-document files', function () {
    $admin = userWithRole('administrator');
    $pdf = storedPdf();
    $validator = app(BlockTreeValidator::class);

    $clean = $validator->validate([['type' => 'document', 'content' => ['file' => ['$media' => $pdf->id], 'title' => 'Field guide']]], $admin);
    $block = app(BlockPayloadResolver::class)->resolve($clean)[0];
    expect($block['content']['file'])->toMatchArray(['url' => $pdf->url(), 'extension' => 'pdf', 'name' => 'guide.pdf'])
        ->and($block['content']['title'])->toBe('Field guide');

    $private = storedPdf('internal.pdf', private: true);
    expect(fn () => $validator->validate([['type' => 'document', 'content' => ['file' => ['$media' => $private->id]]]], $admin))
        ->toThrow(ValidationException::class);

    $image = app(MediaService::class)->store(fakeJpeg(), $admin);
    expect(fn () => $validator->validate([['type' => 'document', 'content' => ['file' => ['$media' => $image->id]]]], $admin))
        ->toThrow(ValidationException::class);

    // Used on a page, the file cannot be deleted from the library.
    $page = makePage($admin);
    app(PageService::class)->update($admin, $page, ['blocks' => [['type' => 'document', 'content' => ['file' => ['$media' => $pdf->id]]]]], $page->lock_version);
    expect(fn () => app(MediaService::class)->delete($pdf, $admin))->toThrow(ValidationException::class);
});

it('saves a whole page as a template', function () {
    $editor = userWithRole('editor');

    $this->actingAs($editor)->postJson(route('admin.api.templates.store'), [
        'name' => 'Campaign page layout',
        'scope' => 'page',
        'blocks' => [
            ['type' => 'heading', 'content' => ['text' => 'Campaign', 'level' => '2']],
            ['type' => 'rich-text', 'content' => ['html' => '<p>Intro</p>']],
        ],
    ])->assertCreated();

    $template = BlockTemplate::query()->where('name', 'Campaign page layout')->firstOrFail();
    $this->actingAs($editor)->getJson(route('admin.api.templates.show', $template))
        ->assertOk()
        ->assertJsonCount(2, 'blocks');
});
