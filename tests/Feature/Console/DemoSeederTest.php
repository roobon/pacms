<?php

use App\Enums\TestimonialStatus;
use App\Models\BlockTemplate;
use App\Models\MediaCoverage;
use App\Models\News;
use App\Models\Page;
use App\Models\Testimonial;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Storage;

it('fills every area with linked demo content through the real services', function () {
    Storage::fake((string) config('pacms.media.disk'));
    Storage::fake((string) config('pacms.media.private_disk'));
    userWithRole('super-admin');

    $this->seed(DemoSeeder::class);

    expect(News::query()->published()->count())->toBe(6)
        ->and(News::query()->where('status', 'in_review')->exists())->toBeTrue()
        ->and(Testimonial::query()->published()->count())->toBe(4)
        ->and(Testimonial::query()->where('status', TestimonialStatus::Pending)->exists())->toBeTrue()
        ->and(MediaCoverage::query()->where('availability', 'unavailable')->exists())->toBeTrue()
        ->and(Page::query()->live()->count())->toBe(5);

    // The home page is set and every collection block on it has items.
    $home = $this->getJson('/api/v1/resolve?path=%2F')->assertOk()->json('data.blocks');
    $counts = collect($home)->flatMap(fn ($node) => $node['type'] === 'section' ? $node['children'] : [$node])
        ->filter(fn ($node) => isset($node['items']))->mapWithKeys(fn ($node) => [$node['type'] => count($node['items'])]);
    expect($counts->all())->toMatchArray(['news' => 3, 'testimonials' => 4, 'media-coverage' => 3, 'partners' => 6, 'type/success_stories' => 3]);

    // Links between items: the project shows its manager, partners, gallery and documents.
    $project = $this->getJson('/api/v1/resolve?path=/projects/coastal-mangrove-restoration')->assertOk()->json('data');
    expect(collect($project['facts'])->pluck('value'))->toContain('Mitu Chowdhury')
        ->and(collect($project['related'])->pluck('key')->all())->toContain('partners', 'gallery')
        ->and($project['documents'][0]['label'])->toBe('Project plan (PDF)');

    // Content types made in the admin, with items.
    $this->getJson('/api/v1/resolve?path=/success-stories')->assertJsonPath('kind', 'archive')->assertJsonCount(3, 'data.items')->assertJsonCount(2, 'data.categories');
    $this->getJson('/api/v1/resolve?path=/success-stories/green-flag-for-dhaka-model-school')
        ->assertJsonPath('data.category', 'Green Flag schools')
        ->assertJsonPath('data.documents.0.label', 'Green Flag assessment (PDF)');
    // Coverage carries tags besides its category.
    $this->getJson('/api/v1/resolve?path=/media-coverage/'.MediaCoverage::query()->where('source_name', 'The Daily Star')->value('slug'))
        ->assertJsonPath('data.taxonomies.0.terms.1.name', 'Youth');

    // Menus, header and footer (Phase 9).
    $chrome = $this->getJson('/api/v1/site')->json('data.chrome');
    $menu = collect($chrome['header'][0]['children'][0]['children'][0]['children'][1]['children'])->firstWhere('type', 'menu');
    expect(array_column($menu['data']['items'], 'label'))->toContain('About us', 'Programmes', 'News & media')
        ->and($chrome['footer'])->not->toBeNull()
        ->and(collect($menu['data']['items'])->firstWhere('label', 'Programmes')['panel'][0]['type'])->toBe('columns');

    // An external feed source with stored items, shown by a Feed block (Phase 10).
    $blocks = $this->getJson('/api/v1/resolve?path=/our-programmes')->json('data.blocks');
    $feed = collect($blocks)->flatMap(fn ($node) => $node['children'] ?? [])->firstWhere('type', 'feed');
    expect($feed['items'])->toHaveCount(3)->and($feed['items'][0]['meta']['source'])->toBe('Partner news (demo)');

    // The starter kit's templates and global blocks are installed too.
    expect(BlockTemplate::query()->where('is_system', true)->count())->toBe(23);

    // Running it again on a site with content is refused (use --fresh).
    $this->artisan('pacms:demo')->assertFailed();
});
