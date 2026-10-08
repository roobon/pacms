<?php

use App\Cms\Blocks\BlockPayloadResolver;
use App\Cms\Blocks\BlockTreeValidator;
use App\Cms\Content\ContentTypeRegistry;
use App\Enums\WorkflowAction;
use App\Models\ContentItem;
use App\Models\Gallery;
use App\Models\Media;
use App\Models\Partner;
use App\Models\TeamMember;
use App\Models\Term;
use App\Services\Content\ContentService;
use App\Services\Media\MediaService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake((string) config('pacms.media.disk'));
    Storage::fake((string) config('pacms.media.private_disk'));
});

/**
 * @param  array<string, mixed>  $data
 */
function activeItem(string $type, array $data): ContentItem
{
    $definition = app(ContentTypeRegistry::class)->get($type);
    $service = app(ContentService::class);
    $admin = userWithRole('super-admin');
    $item = $service->create($definition, $admin, $data);
    $service->transition($definition, $item, WorkflowAction::Publish, $admin);

    return $item->fresh();
}

function photo(bool $private = false): Media
{
    return app(MediaService::class)->store(fakeJpeg('photo-'.uniqid().'.jpg'), userWithRole('super-admin'), ['alt' => 'Children planting trees'], $private);
}

it('manages team members with one permission and simple active / inactive states', function () {
    $editor = userWithRole('editor');
    $department = Term::query()->create(['taxonomy' => 'department', 'name' => 'Programmes', 'slug' => 'programmes']);

    $this->actingAs($editor)->post(route('admin.team.store'), [
        'title' => 'Nusrat Jahan', 'designation' => 'Programme Coordinator', 'email' => 'nusrat@example.org', 'show_email' => '0',
        'phone' => '+880 1700 000000', 'show_phone' => '1', 'position' => '2', 'terms' => [$department->id],
        'social_links' => [['network' => 'LinkedIn', 'url' => 'https://linkedin.com/in/nusrat']],
    ])->assertSessionHasNoErrors();
    $member = TeamMember::query()->firstOrFail();

    // Authors have no team.manage.
    $this->actingAs(userWithRole('author', twoFactor: false))->get(route('admin.team.index'))->assertForbidden();

    // Only "make active / inactive": scheduling is refused.
    $this->actingAs($editor)->post(route('admin.team.workflow', $member), ['action' => 'schedule', 'publish_at' => now()->addDay()->format('Y-m-d\TH:i')])
        ->assertSessionHasErrors('action');
    $this->actingAs($editor)->get(route('admin.team.edit', $member))->assertOk()->assertSee('Name')->assertSee('Photo')->assertDontSee('>Submit<', false);
    $this->actingAs($editor)->post(route('admin.team.workflow', $member), ['action' => 'publish'])->assertRedirect();

    $this->getJson('/api/v1/resolve?path=/team/'.$member->slug)
        ->assertJsonPath('kind', 'content')
        ->assertJsonPath('data.facts.0.value', 'Programme Coordinator')
        ->assertJsonPath('data.facts.1.value', 'Programmes')
        ->assertJsonPath('data.actions.0.url', 'tel:+8801700000000')
        ->assertJsonPath('data.actions.1.url', 'https://linkedin.com/in/nusrat')
        ->assertJsonMissing(['url' => 'mailto:nusrat@example.org']);

    $this->get('/team/'.$member->slug)->assertOk()->assertSee('"@type":"Person"', false)->assertDontSee('nusrat@example.org');
});

it('lists team members and partners in their display order', function () {
    activeItem('team', ['title' => 'Second', 'position' => 2]);
    activeItem('team', ['title' => 'First', 'position' => 1]);

    $this->getJson('/api/v1/resolve?path=/team')
        ->assertJsonPath('data.items.0.title', 'First')
        ->assertJsonPath('data.items.1.title', 'Second');

    $clean = app(BlockTreeValidator::class)->validate([['type' => 'team']], userWithRole('super-admin'));
    expect(array_column(app(BlockPayloadResolver::class)->resolve($clean)[0]['items'], 'title'))->toBe(['First', 'Second']);
});

it('gives partners no pages of their own: cards and logos link to their websites', function () {
    $partner = activeItem('partners', ['title' => 'Green Earth Trust', 'website_url' => 'https://greenearth.example', 'featured_media_id' => photo()->id]);

    $this->getJson('/api/v1/resolve?path=/partners/'.$partner->slug)->assertJsonPath('kind', 'not_found');
    $this->getJson('/api/v1/resolve?path=/partners')
        ->assertJsonPath('kind', 'archive')
        ->assertJsonPath('data.items.0.url', 'https://greenearth.example')
        ->assertJsonPath('data.items.0.external', true);

    $clean = app(BlockTreeValidator::class)->validate([['type' => 'partners']], userWithRole('super-admin'));
    $block = app(BlockPayloadResolver::class)->resolve($clean)[0];
    expect($block['content']['style'] ?? 'logos')->toBe('logos')
        ->and($block['items'][0]['title'])->toBe('Green Earth Trust');

    $this->get('/sitemaps/partners.xml')->assertOk()->assertDontSee($partner->slug);
});

it('builds a gallery from library photos and videos, shown with captions', function () {
    $editor = userWithRole('editor');
    $first = photo();
    $second = photo();

    $this->actingAs($editor)->post(route('admin.galleries.store'), [
        'title' => 'Tree fair 2026', 'gallery_type' => 'mixed', 'gallery_date' => '2026-03-12', 'location' => 'Dhaka', 'credit' => 'R. Ahmed',
        'gallery_items' => [
            ['media_id' => $second->id, 'caption' => 'Opening', 'alt_override' => 'Guests at the gate'],
            ['video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'caption' => 'Highlights'],
            ['media_id' => $first->id],
            ['caption' => 'empty row is dropped'],
        ],
    ])->assertSessionHasNoErrors();

    $gallery = Gallery::query()->firstOrFail();
    expect($gallery->items()->pluck('media_id')->all())->toBe([$second->id, null, $first->id]);

    // A photo in a gallery cannot be deleted from the library.
    expect(fn () => app(MediaService::class)->delete($first, userWithRole('super-admin')))->toThrow(ValidationException::class);

    $this->actingAs($editor)->post(route('admin.galleries.workflow', $gallery), ['action' => 'publish']);
    $this->getJson('/api/v1/resolve?path=/galleries/tree-fair-2026')
        ->assertJsonPath('data.gallery.0.kind', 'image')
        ->assertJsonPath('data.gallery.0.image.alt', 'Guests at the gate')
        ->assertJsonPath('data.gallery.0.caption', 'Opening')
        ->assertJsonPath('data.gallery.1.kind', 'video')
        ->assertJsonPath('data.gallery.1.video.provider', 'youtube')
        ->assertJsonPath('data.facts.0.value', '12 March 2026');

    // The Gallery block shows one chosen gallery's media.
    $clean = app(BlockTreeValidator::class)->validate([['type' => 'gallery', 'source' => ['mode' => 'dynamic', 'filters' => ['gallery' => $gallery->id]]]], userWithRole('super-admin'));
    $block = app(BlockPayloadResolver::class)->resolve($clean)[0];
    expect($block['items'][0]['media'])->toHaveCount(3);
});

it('refuses private photos and links that are not YouTube or Vimeo', function () {
    $editor = userWithRole('editor');

    $this->actingAs($editor)->post(route('admin.galleries.store'), ['title' => 'Bad', 'gallery_type' => 'photo', 'gallery_items' => [['media_id' => photo(private: true)->id]]])
        ->assertSessionHasErrors('gallery_items.0.media_id');
    $this->actingAs($editor)->post(route('admin.galleries.store'), ['title' => 'Bad', 'gallery_type' => 'video', 'gallery_items' => [['video_url' => 'https://evil.example/video']]])
        ->assertSessionHasErrors('gallery_items.0.video_url');
});

it('links a project to its manager, partners and gallery', function () {
    $manager = activeItem('team', ['title' => 'A. Rahman']);
    $later = activeItem('partners', ['title' => 'Zeta Fund', 'position' => 2, 'website_url' => 'https://zeta.example']);
    $earlier = activeItem('partners', ['title' => 'Alpha Trust', 'position' => 1]);
    $inactive = app(ContentService::class)->create(app(ContentTypeRegistry::class)->get('partners'), userWithRole('super-admin'), ['title' => 'Hidden partner']);
    $gallery = activeItem('galleries', ['title' => 'Site visit', 'gallery_type' => 'photo', 'gallery_items' => [['media_id' => photo()->id]]]);

    $this->actingAs(userWithRole('editor'))->post(route('admin.projects.store'), [
        'title' => 'Mangroves', 'project_status' => 'ongoing', 'manager_name' => 'Old text',
        'manager' => [$manager->id], 'partners' => [$later->id, $inactive->id, $earlier->id], 'gallery' => [$gallery->id],
    ])->assertSessionHasNoErrors();
    $project = \App\Models\Project::query()->firstOrFail();
    $this->actingAs(userWithRole('editor'))->post(route('admin.projects.workflow', $project), ['action' => 'publish']);

    $response = $this->getJson('/api/v1/resolve?path=/projects/mangroves')->assertOk();
    $facts = collect($response->json('data.facts'));
    expect($facts->firstWhere('label', 'Project manager'))->toMatchArray(['value' => 'A. Rahman', 'url' => '/team/'.$manager->slug])
        ->and($facts->where('label', 'Project manager'))->toHaveCount(1);

    $related = collect($response->json('data.related'))->keyBy('key');
    expect(array_column($related['partners']['items'], 'title'))->toBe(['Alpha Trust', 'Zeta Fund'])
        ->and($related['gallery']['display'])->toBe('gallery')
        ->and($related['gallery']['items'])->toHaveCount(1);
});

it('restores relations and gallery items from a revision', function () {
    $type = app(ContentTypeRegistry::class)->get('galleries');
    $service = app(ContentService::class);
    $admin = userWithRole('super-admin');
    $event = activeItem('events', ['title' => 'Fair', 'start_at' => '2030-01-01T10:00', 'timezone' => 'Asia/Dhaka']);
    $image = photo();

    $gallery = $service->create($type, $admin, ['title' => 'Fair photos', 'gallery_type' => 'photo', 'event' => [$event->id], 'gallery_items' => [['media_id' => $image->id, 'caption' => 'First']]]);
    $service->update($type, $admin, $gallery, ['title' => 'Fair photos', 'gallery_type' => 'photo', 'event' => [], 'gallery_items' => []], $gallery->lock_version);
    expect($gallery->fresh()->items()->count())->toBe(0)->and($gallery->fresh()->relatedIds('event'))->toBe([]);

    $service->restore($type, $admin, $gallery->fresh(), $gallery->revisions()->where('number', 1)->firstOrFail());
    $gallery->refresh();
    expect($gallery->relatedIds('event'))->toBe([$event->id])
        ->and($gallery->items()->first()?->caption)->toBe('First');
});
