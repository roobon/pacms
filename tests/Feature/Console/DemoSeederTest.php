<?php

use App\Enums\TestimonialStatus;
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
    expect($counts->all())->toMatchArray(['news' => 3, 'testimonials' => 4, 'media-coverage' => 3, 'partners' => 6]);

    // Links between items: the project shows its manager, partners, gallery and documents.
    $project = $this->getJson('/api/v1/resolve?path=/projects/coastal-mangrove-restoration')->assertOk()->json('data');
    expect(collect($project['facts'])->pluck('value'))->toContain('Mitu Chowdhury')
        ->and(collect($project['related'])->pluck('key')->all())->toContain('partners', 'gallery')
        ->and($project['documents'][0]['label'])->toBe('Project plan (PDF)');

    // Running it again on a site with content is refused (use --fresh).
    $this->artisan('pacms:demo')->assertFailed();
});
