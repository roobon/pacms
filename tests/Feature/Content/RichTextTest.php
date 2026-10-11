<?php

use App\Cms\Exchange\DocumentReader;
use App\Cms\Exchange\ImportReport;
use App\Cms\Exchange\PortableTranslator;
use App\Models\News;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Support\Facades\Storage;

it('keeps the editor\'s formatting and removes everything else', function () {
    $media = rtrim(Storage::disk((string) config('pacms.media.disk'))->url('media'), '/');
    $html = app(HtmlSanitizer::class)->sanitize(
        '<p class="pa-align-center evil">Hi <span class="pa-text-accent">x</span> <mark class="pa-mark-warning">m</mark></p>'
        .'<div class="pa-callout pa-callout--info"><p>Box</p></div>'
        .'<figure class="pa-figure pa-figure--left"><img src="'.$media.'/2026/10/a.jpg" alt="A" width="10" data-media="5" onerror="alert(1)"><figcaption>Caption</figcaption></figure>'
        .'<figure class="pa-figure"><img src="https://evil.example/x.jpg"><figcaption>Gone</figcaption></figure>'
        .'<table><tbody><tr><th>Year</th></tr><tr><td colspan="2">2026</td></tr></tbody></table>'
        .'<p style="color:red" class="pa-text-hotpink">styled</p>'
    );

    expect($html)->toContain('<p class="pa-align-center">Hi <span class="pa-text-accent">x</span> <mark class="pa-mark-warning">m</mark></p>')
        ->and($html)->toContain('<div class="pa-callout pa-callout--info"><p>Box</p></div>')
        ->and($html)->toContain('<figure class="pa-figure pa-figure--left"><img src="'.$media.'/2026/10/a.jpg" alt="A" width="10" data-media="5" /><figcaption>Caption</figcaption></figure>')
        ->and($html)->toContain('<td colspan="2">2026</td>')
        ->and($html)->toContain('<p>styled</p>')
        ->and($html)->not->toContain('evil')
        ->and($html)->not->toContain('onerror')
        ->and($html)->not->toContain('Gone')
        ->and($html)->not->toContain('style=')
        ->and($html)->not->toContain('hotpink');
});

it('saves article text with images, tables and boxes', function () {
    $editor = userWithRole('editor');
    $body = '<h2 class="pa-align-center">Results</h2><div class="pa-callout pa-callout--success"><p>Two tonnes collected.</p></div><table><tbody><tr><th>Site</th><th>Kg</th></tr><tr><td>River</td><td>2000</td></tr></tbody></table>';
    $this->actingAs($editor)->post(route('admin.news.store'), ['title' => 'Clean-up', 'body' => $body])->assertSessionHasNoErrors();

    expect(News::query()->firstOrFail()->body)->toContain('<h2 class="pa-align-center">Results</h2>')->toContain('pa-callout--success')->toContain('<th>Kg</th>');
    $this->actingAs($editor)->get(route('admin.news.create'))->assertOk()->assertSee('pacms-admin-endpoints', false);
});

it('reads a plain "url" on a button as its link (AI-made JSON)', function () {
    $report = new ImportReport;
    $document = app(DocumentReader::class)->read(json_encode(['schema_version' => '1.0', 'kind' => 'block', 'blocks' => [
        ['type' => 'button', 'content' => ['label' => 'Get involved', 'url' => '/get-involved']],
    ]]), $report);
    $nodes = app(PortableTranslator::class)->nodes($document['blocks'], '/blocks', [], $report)['nodes'];

    expect($nodes[0]['content']['link'])->toMatchArray(['type' => 'url', 'url' => '/get-involved'])
        ->and($report->hasErrors())->toBeFalse()
        ->and(collect($report->toArray()['entries'])->where('level', 'warning'))->toHaveCount(0);
});
