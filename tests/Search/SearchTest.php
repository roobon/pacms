<?php

use App\Cms\Content\ContentTypeRegistry;
use App\Enums\WorkflowAction;
use App\Models\ContentItem;
use App\Models\SearchDocument;
use App\Services\Content\ContentService;
use App\Services\Content\ContentTypeService;
use App\Services\Publishing\PublishingService;
use App\Services\Search\SearchService;

/**
 * @param  array<string, mixed>  $data
 */
function searchItem(string $type, array $data, bool $publish = true): ContentItem
{
    $definition = app(ContentTypeRegistry::class)->get($type);
    $service = app(ContentService::class);
    $admin = userWithRole('super-admin');
    $item = $service->create($definition, $admin, $data);
    if ($publish) {
        $service->transition($definition, $item, WorkflowAction::Publish, $admin);
    }

    return $item->fresh();
}

it('finds published pages and items by their text, blocks included, and never drafts', function () {
    livePage([
        'title' => 'Our approach',
        'blocks' => [
            ['type' => 'heading', 'content' => ['text' => 'Mangrove restoration', 'level' => '2']],
            ['type' => 'rich-text', 'content' => ['html' => '<p>Coastal schools plant <strong>seedlings</strong> every monsoon.</p>']],
        ],
    ]);
    searchItem('news', ['title' => 'Students plant mangroves in Khulna', 'excerpt' => 'A record day for the coast.']);
    searchItem('news', ['title' => 'Draft about mangroves'], publish: false);
    searchItem('partners', ['title' => 'Mangrove Partners Ltd']);

    $response = $this->getJson('/api/v1/search?q=mangrove+seedlings')->assertOk();
    $titles = array_column($response->json('data'), 'title');

    expect($titles)->toContain('Our approach')
        ->toContain('Students plant mangroves in Khulna')
        ->not->toContain('Draft about mangroves')
        // Partners have no pages: not in search.
        ->not->toContain('Mangrove Partners Ltd');
    $page = collect($response->json('data'))->firstWhere('title', 'Our approach');
    expect($page['type'])->toBe('pages')
        ->and($page['url'])->toBe('/our-approach')
        ->and($page['excerpt'])->toContain('seedlings')
        ->and($response->json('meta.types'))->toHaveKey('media_coverage');
});

it('removes items from search when they are unpublished or deleted', function () {
    $item = searchItem('events', ['title' => 'Beach clean-up day', 'start_at' => '2027-03-01 09:00', 'timezone' => 'Asia/Dhaka']);
    expect(SearchDocument::query()->where('type', 'events')->count())->toBe(1);

    $type = app(ContentTypeRegistry::class)->get('events');
    app(ContentService::class)->transition($type, $item, WorkflowAction::Unpublish, userWithRole('super-admin'));
    expect(SearchDocument::query()->count())->toBe(0);
    $this->getJson('/api/v1/search?q=beach')->assertJsonCount(0, 'data');

    $page = livePage(['title' => 'Volunteer with us']);
    expect(SearchDocument::query()->where('type', 'pages')->count())->toBe(1);
    app(PublishingService::class)->transition($page, WorkflowAction::Archive, userWithRole('super-admin'));
    expect(SearchDocument::query()->count())->toBe(0);
});

it('filters by type, paginates, and treats the query as plain words', function () {
    foreach (range(1, 3) as $i) {
        searchItem('news', ['title' => "Recycling drive part {$i}"]);
    }
    searchItem('publications', ['title' => 'Recycling handbook']);

    $this->getJson('/api/v1/search?q=recycling&type[]=publications')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type_label', 'Publications');
    $this->getJson('/api/v1/search?q=recycling&per_page=2&page=2')
        ->assertJsonPath('meta.total', 4)
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonCount(2, 'data');

    // Boolean operators cannot change the query.
    expect(SearchService::clean('+recycling -drive "handbook*" (x)'))->toBe('recycling drive handbook x');
    $this->getJson('/api/v1/search?q=-recycling')->assertJsonPath('meta.total', 4);

    $this->getJson('/api/v1/search?q=a')->assertJsonValidationErrors('q');
    $this->getJson('/api/v1/search?q=recycling&type[]=users')->assertJsonValidationErrors('type.0');
});

it('rebuilds the whole index from what is published', function () {
    searchItem('programs', ['title' => 'Eco-Schools']);
    livePage(['title' => 'Contact']);
    SearchDocument::query()->delete();

    $this->artisan('pacms:search:rebuild')->expectsOutputToContain('Indexed 2')->assertSuccessful();
    $this->getJson('/api/v1/search?q=eco-schools')->assertJsonPath('data.0.title', 'Eco-Schools');
});

it('finds items of content types made in the admin, by their own fields too', function () {
    app(ContentTypeService::class)->create(userWithRole('super-admin'), [
        'label' => 'Success stories', 'workflow' => 'editorial',
        'fields' => [['key' => 'school', 'type' => 'text', 'label' => 'School'], ['key' => 'note', 'type' => 'textarea', 'label' => 'Note']],
        'display' => ['note' => 'hidden'],
    ]);
    searchItem('success_stories', ['title' => 'Green Flag day', 'school' => 'Sundarbans Academy', 'note' => 'Confidential budget']);

    $this->getJson('/api/v1/search?q=sundarbans')
        ->assertJsonPath('data.0.title', 'Green Flag day')
        ->assertJsonPath('data.0.type_label', 'Success stories')
        ->assertJsonPath('data.0.url', '/success-stories/green-flag-day');
    // Hidden fields are not searchable.
    $this->getJson('/api/v1/search?q=confidential')->assertJsonPath('meta.total', 0);
});
