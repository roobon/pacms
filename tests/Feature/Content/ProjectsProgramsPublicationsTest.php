<?php

use App\Cms\Blocks\BlockPayloadResolver;
use App\Cms\Blocks\BlockTreeValidator;
use App\Cms\Content\ContentTypeRegistry;
use App\Enums\WorkflowAction;
use App\Models\Attachment;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\Program;
use App\Models\Project;
use App\Services\Content\ContentService;
use App\Services\Media\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake((string) config('pacms.media.disk'));
    Storage::fake((string) config('pacms.media.private_disk'));
});

function pdf(string $name = 'report.pdf', bool $private = false): Media
{
    $file = UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\n% ".uniqid()."\ntrailer<<>>\n%%EOF");

    return app(MediaService::class)->store($file, userWithRole('super-admin'), [], $private);
}

/**
 * @param  array<string, mixed>  $data
 */
function publishedItem(string $type, array $data): ContentItem
{
    $definition = app(ContentTypeRegistry::class)->get($type);
    $service = app(ContentService::class);
    $editor = userWithRole('editor');
    $item = $service->create($definition, $editor, $data);
    $service->transition($definition, $item, WorkflowAction::Publish, $editor);

    return $item->fresh();
}

it('shows the admin forms of the three modules with their own sections', function () {
    $editor = userWithRole('editor');

    $this->actingAs($editor)->get(route('admin.projects.create'))->assertOk()
        ->assertSee('Project details')->assertSee('Project status')->assertSee('Documents');
    $this->actingAs($editor)->get(route('admin.programs.create'))->assertOk()
        ->assertSee('Objectives &amp; activities', false)->assertSee('data-repeater-field', false)->assertSee('Documents');
    $this->actingAs($editor)->get(route('admin.publications.create'))->assertOk()
        ->assertSee('Publication details')->assertSee('data-kind="document"', false)->assertDontSee('data-documents-field', false);

    $this->actingAs($editor)->get(route('admin.dashboard'))
        ->assertSee(route('admin.projects.index'), false)
        ->assertSee(route('admin.programs.index'), false)
        ->assertSee(route('admin.publications.index'), false)
        ->assertSee('Publication categories');
});

it('creates a project with status, dates and documents, and shows them publicly', function () {
    $editor = userWithRole('editor');
    $report = pdf('baseline-survey.pdf');
    $brief = pdf('brief.pdf');

    $this->actingAs($editor)->post(route('admin.projects.store'), [
        'title' => 'Mangrove restoration',
        'project_status' => 'ongoing',
        'start_date' => '2024-03-01',
        'location' => 'Khulna',
        'manager_name' => 'A. Rahman',
        'website_url' => 'https://example.org/mangroves',
        'documents' => [['media_id' => $brief->id, 'label' => ''], ['media_id' => $report->id, 'label' => 'Baseline survey']],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $project = Project::query()->firstOrFail();
    expect($project->attachments()->pluck('media_id')->all())->toBe([$brief->id, $report->id])
        ->and($project->type()->formValue($project, 'start_date'))->toBe('2024-03-01');

    // A document in use cannot be deleted from the library.
    expect(fn () => app(MediaService::class)->delete($report, userWithRole('super-admin')))->toThrow(ValidationException::class);

    $this->actingAs($editor)->post(route('admin.projects.workflow', $project), ['action' => 'publish']);
    $this->getJson('/api/v1/resolve?path=/projects/mangrove-restoration')
        ->assertJsonPath('kind', 'content')
        ->assertJsonPath('data.facts.0.value', 'Ongoing')
        ->assertJsonPath('data.facts.1.value', 'Since March 2024')
        ->assertJsonPath('data.actions.0.url', 'https://example.org/mangroves')
        ->assertJsonPath('data.documents.0.label', 'brief.pdf')
        ->assertJsonPath('data.documents.1.label', 'Baseline survey')
        ->assertJsonPath('data.documents.1.extension', 'PDF')
        ->assertJsonPath('data.show_date', false);

    $this->get('/projects/mangrove-restoration')->assertOk()->assertSee('"@type":"Project"', false);
});

it('refuses private and non-document files as documents', function () {
    $editor = userWithRole('editor');
    $private = pdf('internal.pdf', private: true);

    $this->actingAs($editor)->post(route('admin.projects.store'), [
        'title' => 'Secret', 'project_status' => 'planned', 'documents' => [['media_id' => $private->id]],
    ])->assertSessionHasErrors('documents.0.media_id');

    $this->actingAs($editor)->post(route('admin.publications.store'), ['title' => 'Paper', 'document_media_id' => $private->id])
        ->assertSessionHasErrors('document_media_id');

    $this->actingAs($editor)->post(route('admin.projects.store'), ['title' => 'Bad', 'project_status' => 'sleeping'])
        ->assertSessionHasErrors('project_status');
    $this->actingAs($editor)->post(route('admin.projects.store'), ['title' => 'Bad', 'project_status' => 'ongoing', 'start_date' => '2025-01-01', 'end_date' => '2024-01-01'])
        ->assertSessionHasErrors('end_date');
});

it('lists projects by status in the archive and the Projects block', function () {
    publishedItem('projects', ['title' => 'Ongoing one', 'project_status' => 'ongoing']);
    publishedItem('projects', ['title' => 'Finished one', 'project_status' => 'completed']);

    $this->getJson('/api/v1/resolve?path=/projects')->assertJsonPath('data.view', 'all')->assertJsonCount(2, 'data.items');
    $this->getJson('/api/v1/resolve?path=/projects&view=completed')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.title', 'Finished one')
        ->assertJsonPath('data.items.0.meta.status', 'Completed');

    $clean = app(BlockTreeValidator::class)->validate([['type' => 'projects', 'source' => ['mode' => 'dynamic', 'filters' => ['project_status' => 'ongoing']]]], userWithRole('super-admin'));
    $block = app(BlockPayloadResolver::class)->resolve($clean)[0];
    expect(array_column($block['items'], 'title'))->toBe(['Ongoing one']);
});

it('saves program objectives and activities as ordered lists', function () {
    $editor = userWithRole('editor');

    $this->actingAs($editor)->post(route('admin.programs.store'), [
        'title' => 'Green schools',
        'objectives' => [['text' => 'Plant trees'], ['text' => '  '], ['text' => 'Teach recycling']],
        'activities' => [['title' => 'Eco clubs', 'text' => "Weekly meetings\nin 40 schools"]],
    ])->assertSessionHasNoErrors();

    $program = Program::query()->firstOrFail();
    expect($program->objectives)->toBe([['text' => 'Plant trees'], ['text' => 'Teach recycling']])
        ->and($program->activities)->toEqual([['title' => 'Eco clubs', 'text' => "Weekly meetings\nin 40 schools"]]);

    $this->actingAs($editor)->post(route('admin.programs.workflow', $program), ['action' => 'publish']);
    $this->getJson('/api/v1/resolve?path=/programs/green-schools')
        ->assertJsonPath('data.lists.0.title', 'Objectives')
        ->assertJsonPath('data.lists.0.items.1.text', 'Teach recycling')
        ->assertJsonPath('data.lists.1.items.0.title', 'Eco clubs');

    // Removing every row clears the list (the browser sends nothing for it).
    $program->refresh();
    $this->actingAs($editor)->put(route('admin.programs.update', $program), ['title' => 'Green schools', 'lock_version' => $program->lock_version])->assertSessionHasNoErrors();
    expect($program->fresh()->objectives)->toBeNull();

    $this->actingAs($editor)->post(route('admin.programs.store'), ['title' => 'Too long', 'objectives' => [['text' => str_repeat('x', 501)]]])
        ->assertSessionHasErrors('objectives.0.text');
});

it('publishes a publication with a download, newest publication first', function () {
    $document = pdf('annual-report.pdf');
    $older = publishedItem('publications', ['title' => 'Annual report 2023', 'publication_date' => '2024-02-01', 'author_text' => 'Research team', 'document_media_id' => $document->id]);
    publishedItem('publications', ['title' => 'Annual report 2024', 'publication_date' => '2025-02-01']);

    $this->getJson('/api/v1/resolve?path=/publications')
        ->assertJsonPath('data.items.0.title', 'Annual report 2024')
        ->assertJsonPath('data.items.1.title', 'Annual report 2023');

    $this->getJson('/api/v1/resolve?path=/publications/'.$older->slug)
        ->assertJsonPath('data.facts.0.value', '1 February 2024')
        ->assertJsonPath('data.actions.0.label', 'Download PDF')
        ->assertJsonPath('data.actions.0.download', true)
        ->assertJsonPath('data.actions.0.url', $document->url());

    $this->get('/publications/'.$older->slug)->assertOk()
        ->assertSee('"@type":"CreativeWork"', false)
        ->assertSee('"datePublished":"2024-02-01"', false);
});

it('restores documents and lists from a revision, and removes documents with the item', function () {
    $editor = userWithRole('editor');
    $first = pdf('first.pdf');
    $type = app(ContentTypeRegistry::class)->get('programs');
    $service = app(ContentService::class);

    $program = $service->create($type, $editor, ['title' => 'Water', 'objectives' => [['text' => 'Clean wells']], 'documents' => [['media_id' => $first->id, 'label' => 'Plan']]]);
    $service->update($type, $editor, $program, ['title' => 'Water', 'objectives' => [], 'documents' => []], $program->lock_version);
    expect($program->fresh()->attachments)->toHaveCount(0);

    $revision = $program->revisions()->where('number', 1)->firstOrFail();
    $service->restore($type, $editor, $program->fresh(), $revision);
    $program->refresh();
    expect($program->objectives)->toBe([['text' => 'Clean wells']])
        ->and($program->attachments()->first()?->label)->toBe('Plan');

    $service->delete($type, $editor, $program);
    expect(Attachment::query()->count())->toBe(0);
    app(MediaService::class)->delete($first, userWithRole('super-admin'));
    expect(Media::query()->whereKey($first->id)->exists())->toBeFalse();
});

it('lists every module in the sitemap, without hidden items', function () {
    publishedItem('projects', ['title' => 'Visible project', 'project_status' => 'ongoing']);
    publishedItem('projects', ['title' => 'Hidden project', 'project_status' => 'ongoing', 'seo' => ['robots_index' => false]]);

    $this->get('/sitemap.xml')->assertOk()
        ->assertSee(url('/sitemaps/projects.xml'), false)
        ->assertDontSee(url('/sitemaps/programs.xml'), false);

    $this->get('/sitemaps/projects.xml')->assertOk()
        ->assertSee(url('/projects'), false)
        ->assertSee(url('/projects/visible-project'), false)
        ->assertDontSee('hidden-project', false);

    $this->get('/sitemaps/nothing.xml')->assertNotFound();
});
