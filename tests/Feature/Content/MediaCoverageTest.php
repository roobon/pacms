<?php

use App\Cms\Blocks\BlockPayloadResolver;
use App\Cms\Blocks\BlockTreeValidator;
use App\Cms\Content\ContentTypeRegistry;
use App\Enums\WorkflowAction;
use App\Jobs\CheckMediaCoverageSource;
use App\Models\ActivityLog;
use App\Models\Media;
use App\Models\MediaCoverage;
use App\Models\Program;
use App\Models\Term;
use App\Services\Cache\CacheVersions;
use App\Services\Content\ContentService;
use App\Services\Media\MediaService;
use App\Services\MediaCoverage\SourceChecker;
use App\Support\Http\SafeHttpClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake((string) config('pacms.media.disk'));
    Storage::fake((string) config('pacms.media.private_disk'));
    // Every host resolves to one public address; HTTP answers are faked per test.
    app()->instance(SafeHttpClient::class, new SafeHttpClient(fn (string $host) => $host === 'gone.example' ? [] : ['93.184.216.34']));
});

/**
 * @param  array<string, mixed>  $data
 */
function liveCoverage(array $data = []): MediaCoverage
{
    $type = app(ContentTypeRegistry::class)->get('media_coverage');
    $service = app(ContentService::class);
    $admin = userWithRole('super-admin');
    $item = $service->create($type, $admin, $data + [
        'title' => 'Students plant 10,000 mangroves', 'source_name' => 'The Daily Star', 'source_url' => 'https://news.example/mangroves',
        'coverage_type' => 'newspaper', 'publication_date' => '2026-05-03', 'availability_override' => 'auto',
    ]);
    $service->transition($type, $item, WorkflowAction::Publish, $admin);

    /** @var MediaCoverage */
    return $item->fresh();
}

function archivePdf(bool $private = true): Media
{
    $file = UploadedFile::fake()->createWithContent('clipping.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

    return app(MediaService::class)->store($file, userWithRole('super-admin'), [], $private);
}

it('creates coverage with source, type and date and shows it with a link to the original', function () {
    $editor = userWithRole('editor');
    $program = Program::query()->forceCreate(['title' => 'Eco-Schools', 'slug' => 'eco-schools', 'status' => 'published', 'published_at' => now()->subDay()]);

    $this->actingAs($editor)->get(route('admin.media_coverage.create'))->assertOk()->assertSee('Coverage type')->assertSee('Archived copy (PDF)');
    $this->actingAs($editor)->post(route('admin.media_coverage.store'), [
        'title' => 'Eco-Schools on national TV', 'source_name' => 'Channel i', 'source_url' => 'https://tv.example/eco',
        'coverage_type' => 'tv', 'publication_date' => '2026-04-01', 'availability_override' => 'auto', 'program' => [$program->id],
    ])->assertSessionHasNoErrors();
    $item = MediaCoverage::query()->firstOrFail();
    $this->actingAs($editor)->post(route('admin.media_coverage.workflow', $item), ['action' => 'publish'])->assertRedirect();

    $this->getJson('/api/v1/resolve?path=/media-coverage/'.$item->slug)
        ->assertJsonPath('kind', 'content')
        ->assertJsonPath('data.facts.0.value', 'Channel i')
        ->assertJsonPath('data.facts.1.value', 'TV')
        ->assertJsonPath('data.facts.2.value', '1 April 2026')
        ->assertJsonPath('data.facts.3.value', 'Eco-Schools')
        ->assertJsonPath('data.actions.0.url', 'https://tv.example/eco')
        ->assertJsonPath('data.coverage.display', 'original')
        ->assertJsonMissingPath('data.coverage.archive.pdf');

    // Admin list shows source and type; the edit screen has the source-check box.
    $this->actingAs($editor)->get(route('admin.media_coverage.index'))->assertOk()->assertSee('Channel i · TV');
    $this->actingAs($editor)->get(route('admin.media_coverage.edit', $item))->assertOk()->assertSee('Original link')->assertSee('Check now');
});

it('shows an archived copy only once someone who may publish has confirmed the rights', function () {
    $pdf = archivePdf();
    $item = liveCoverage(['archive_pdf_media_id' => $pdf->id]);

    // No rights confirmed: nothing archived is public, and the file route does not exist.
    $this->getJson('/api/v1/resolve?path=/media-coverage/'.$item->slug)->assertJsonPath('data.coverage.archive', []);
    $this->get(route('media-coverage.archive', ['slug' => $item->slug, 'kind' => 'pdf']))->assertNotFound();

    // An author cannot confirm (the field is ignored for them).
    $author = userWithRole('author', twoFactor: false);
    $draft = app(ContentService::class)->create(app(ContentTypeRegistry::class)->get('media_coverage'), $author, [
        'title' => 'Draft coverage', 'source_name' => 'Prothom Alo', 'coverage_type' => 'newspaper', 'availability_override' => 'auto',
    ]);
    $this->actingAs($author)->put(route('admin.media_coverage.update', $draft), [
        'lock_version' => $draft->lock_version, 'title' => 'Draft coverage', 'source_name' => 'Prothom Alo', 'coverage_type' => 'newspaper',
        'availability_override' => 'auto', 'archive_rights_confirmed' => '1', 'archive_rights_note' => 'I say so',
    ])->assertSessionHasNoErrors();
    expect($draft->fresh()->archive_rights_confirmed)->toBeFalse()->and($draft->fresh()->archive_rights_note)->toBeNull();
    $this->actingAs($author)->get(route('admin.media_coverage.edit', $draft))->assertSee('Only people who may publish media coverage can change this.');

    // An editor confirms; the decision is logged.
    $editor = userWithRole('editor');
    $this->actingAs($editor)->put(route('admin.media_coverage.update', $item), [
        'lock_version' => $item->lock_version, 'title' => $item->title, 'source_name' => 'The Daily Star', 'source_url' => $item->source_url,
        'coverage_type' => 'newspaper', 'availability_override' => 'auto', 'archive_pdf_media_id' => $pdf->id,
        'archive_rights_confirmed' => '1', 'archive_rights_note' => 'Permission by e-mail from the editor, 2 May 2026',
    ])->assertSessionHasNoErrors();
    expect(ActivityLog::query()->where('action', 'media_coverage.archive_rights_confirmed')->where('user_id', $editor->id)->exists())->toBeTrue();

    $url = route('media-coverage.archive', ['slug' => $item->slug, 'kind' => 'pdf'], false);
    $this->getJson('/api/v1/resolve?path=/media-coverage/'.$item->slug)
        ->assertJsonPath('data.coverage.display', 'both')
        ->assertJsonPath('data.coverage.archive.pdf.url', $url)
        // The rights note is internal.
        ->assertDontSee('Permission by e-mail');
    $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    // The private file is still not under /storage.
    expect($pdf->fresh()->isPublic())->toBeFalse();
});

it('falls back to the archive, or a notice, when the original is gone', function () {
    $item = liveCoverage(['archive_pdf_media_id' => archivePdf()->id, 'archive_rights_confirmed' => true]);
    $item->forceFill(['availability' => 'unavailable'])->saveQuietly();

    $this->getJson('/api/v1/resolve?path=/media-coverage/'.$item->slug)
        ->assertJsonPath('data.coverage.display', 'archive')
        ->assertJsonPath('data.actions', [])
        ->assertJsonPath('data.coverage.notice', 'The original is no longer available online. An archived copy is shown instead.');

    $bare = liveCoverage(['title' => 'Radio interview', 'source_url' => 'https://radio.example/1']);
    $bare->forceFill(['availability' => 'unavailable'])->saveQuietly();
    $this->getJson('/api/v1/resolve?path=/media-coverage/'.$bare->slug)
        ->assertJsonPath('data.coverage.display', 'none')
        ->assertJsonPath('data.coverage.notice', 'The original is no longer available online.');

    // Manual override: always show the original link.
    $bare->forceFill(['availability_override' => 'force_original'])->saveQuietly();
    app(CacheVersions::class)->bump('media_coverage');
    $this->getJson('/api/v1/resolve?path=/media-coverage/'.$bare->slug)->assertJsonPath('data.coverage.display', 'original');
});

it('classifies source checks: works, broken three times, or blocked by the site', function () {
    $item = liveCoverage();
    $checker = app(SourceChecker::class);
    $answer = 200;
    Http::fake(function () use (&$answer) {
        if ($answer === 0) {
            throw new ConnectionException('timed out');
        }

        return Http::response('', $answer);
    });

    expect($checker->check($item)->availability)->toBe('available')
        ->and($item->http_status)->toBe(200)
        ->and($item->next_check_at->isFuture())->toBeTrue();

    // 404: counted, but only the third failure in a row marks it unavailable.
    $answer = 404;
    $checker->check($item);
    $checker->check($item);
    expect($item->availability)->toBe('available')->and($item->consecutive_failures)->toBe(2);
    $checker->check($item);
    expect($item->availability)->toBe('unavailable')->and($item->last_check_error)->toContain('404');

    // A site blocking bots (403 on HEAD and GET) or timing out is "unverified" and never counts.
    $answer = 403;
    $checker->check($item);
    expect($item->availability)->toBe('unverified')->and($item->consecutive_failures)->toBe(3);
    $answer = 0;
    expect($checker->check($item)->availability)->toBe('unverified');

    // Working again: failures reset.
    $answer = 200;
    expect($checker->check($item)->consecutive_failures)->toBe(0);

    // A host that no longer exists counts as a failure.
    $gone = liveCoverage(['title' => 'Old portal', 'source_url' => 'https://gone.example/story']);
    expect($checker->check($gone)->consecutive_failures)->toBe(1)->and($gone->last_check_error)->toContain('could not be found');
});

it('follows redirects and asks again with GET when HEAD is refused', function () {
    $item = liveCoverage();
    Http::fake(function ($request) {
        if ($request->url() === 'https://news.example/mangroves') {
            return Http::response('', 301, ['Location' => '/2026/mangroves']);
        }

        return $request->method() === 'HEAD' ? Http::response('', 405) : Http::response('ok', 200);
    });

    expect(app(SourceChecker::class)->check($item)->availability)->toBe('available');
});

it('queues due checks from the scheduler and checks at once from the admin', function () {
    Queue::fake();
    $due = liveCoverage();
    $later = liveCoverage(['title' => 'Later', 'source_url' => 'https://news.example/later']);
    $later->forceFill(['next_check_at' => now()->addHours(5)])->saveQuietly();
    liveCoverage(['title' => 'No link', 'source_url' => null]);

    $this->artisan('pacms:coverage:check')->assertSuccessful();
    Queue::assertPushed(CheckMediaCoverageSource::class, 1);
    Queue::assertPushed(CheckMediaCoverageSource::class, fn ($job) => $job->coverageId === $due->id);
    expect($due->fresh()->next_check_at->isFuture())->toBeTrue();

    Http::fake(['*' => Http::response('', 404)]);
    $this->actingAs(userWithRole('editor'))->post(route('admin.media_coverage.check-source', $due))
        ->assertRedirect(route('admin.media_coverage.edit', $due))
        ->assertSessionHas('warning');
    expect($due->fresh()->consecutive_failures)->toBe(1);

    $this->actingAs(userWithRole('author', twoFactor: false))->post(route('admin.media_coverage.check-source', $due))->assertForbidden();
});

it('lists coverage in the Media Coverage block by type and keeps check details private', function () {
    liveCoverage(['title' => 'On TV', 'source_url' => 'https://tv.example/a', 'coverage_type' => 'tv', 'publication_date' => '2026-01-01']);
    $paper = liveCoverage(['title' => 'In print', 'publication_date' => '2026-02-01']);
    $paper->forceFill(['last_check_error' => 'Internal detail', 'http_status' => 503])->saveQuietly();

    $clean = app(BlockTreeValidator::class)->validate([['type' => 'media-coverage', 'source' => ['mode' => 'dynamic', 'filters' => ['coverage_type' => 'newspaper']]]], userWithRole('super-admin'));
    $block = app(BlockPayloadResolver::class)->resolve($clean)[0];
    expect(array_column($block['items'], 'title'))->toBe(['In print'])
        ->and($block['items'][0]['meta']['source'])->toBe('The Daily Star · Newspaper');

    $this->getJson('/api/v1/resolve?path=/media-coverage')
        ->assertJsonPath('data.items.0.title', 'In print')
        ->assertDontSee('Internal detail')
        ->assertDontSee('http_status');
});

it('tags coverage with the shared tags, besides its category', function () {
    $editor = userWithRole('editor');
    $tag = Term::query()->create(['taxonomy' => 'tag', 'name' => 'Climate', 'slug' => 'climate']);
    $category = Term::query()->create(['taxonomy' => 'media_coverage_category', 'name' => 'Interviews', 'slug' => 'interviews']);

    $this->actingAs($editor)->get(route('admin.media_coverage.create'))->assertOk()->assertSee('Tags')->assertSee('Climate');
    $this->actingAs($editor)->post(route('admin.media_coverage.store'), [
        'title' => 'Tagged coverage', 'source_name' => 'Channel i', 'coverage_type' => 'tv', 'availability_override' => 'auto',
        'terms' => [$tag->id, $category->id],
    ])->assertSessionHasNoErrors();
    $item = MediaCoverage::query()->firstOrFail();
    expect($item->terms()->pluck('name')->sort()->values()->all())->toBe(['Climate', 'Interviews']);
    $this->actingAs($editor)->post(route('admin.media_coverage.workflow', $item), ['action' => 'publish'])->assertRedirect();

    $this->getJson('/api/v1/resolve?path=/media-coverage/'.$item->slug)
        ->assertJsonPath('data.category', 'Interviews')
        ->assertJsonPath('data.taxonomies.0.label', 'Tags')
        ->assertJsonPath('data.taxonomies.0.terms.0.name', 'Climate');

    // Saving with only the category keeps the category and removes the tag.
    $this->actingAs($editor)->put(route('admin.media_coverage.update', $item), [
        'title' => 'Tagged coverage', 'source_name' => 'Channel i', 'coverage_type' => 'tv', 'availability_override' => 'auto',
        'terms' => [$category->id], 'lock_version' => $item->fresh()->lock_version,
    ])->assertSessionHasNoErrors();
    expect($item->terms()->pluck('name')->all())->toBe(['Interviews']);
});
