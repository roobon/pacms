<?php

use App\Cms\Blocks\BlockPayloadResolver;
use App\Cms\Blocks\BlockRegistry;
use App\Cms\Blocks\BlockTreeValidator;
use App\Cms\Blocks\BlockType;
use App\Cms\Sources\ExternalProvider;
use App\Cms\Sources\SourceRegistry;
use App\Enums\ContentStatus;
use App\Enums\WorkflowAction;
use App\Models\News;
use App\Models\Term;
use App\Services\News\NewsService;
use App\Services\Pages\PageService;
use App\Services\Publishing\PublishingService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function news(array $attributes = []): News
{
    $item = new News(['title' => $attributes['title'] ?? 'News '.uniqid(), 'excerpt' => 'Summary', 'featured' => $attributes['featured'] ?? false]);
    $item->slug = $attributes['slug'] ?? Str::slug($item->title).'-'.uniqid();
    $item->forceFill([
        'status' => $attributes['status'] ?? ContentStatus::Published,
        'published_at' => array_key_exists('published_at', $attributes) ? $attributes['published_at'] : now()->subDay(),
    ])->save();

    return $item;
}

function resolveBlocks(array $blocks): array
{
    $clean = app(BlockTreeValidator::class)->validate($blocks, userWithRole('super-admin'));

    return app(BlockPayloadResolver::class)->resolve($clean);
}

it('lists only published news, newest first, limited', function () {
    news(['title' => 'Old', 'published_at' => now()->subDays(10)]);
    news(['title' => 'New', 'published_at' => now()->subDay()]);
    news(['title' => 'Middle', 'published_at' => now()->subDays(5)]);
    news(['title' => 'Draft', 'status' => ContentStatus::Draft]);
    news(['title' => 'Future', 'published_at' => now()->addDay()]);

    $block = resolveBlocks([['type' => 'news', 'source' => ['mode' => 'dynamic', 'entity' => 'news', 'order' => 'latest', 'limit' => 2]]])[0];

    expect(array_column($block['items'], 'title'))->toBe(['New', 'Middle'])
        ->and($block['items'][0])->toHaveKeys(['key', 'kind', 'url', 'excerpt', 'image', 'date'])
        ->and($block['items'][0]['url'])->toStartWith('/news/');
});

it('applies whitelisted filters only', function () {
    $category = Term::create(['taxonomy' => 'news_category', 'name' => 'Climate', 'slug' => 'climate']);
    $tagged = news(['title' => 'Tagged']);
    $tagged->syncTerms('news_category', [$category->id]);
    news(['title' => 'Featured', 'featured' => true]);
    news(['title' => 'Plain']);

    $byCategory = resolveBlocks([['type' => 'news', 'source' => ['mode' => 'dynamic', 'filters' => ['category' => $category->id]]]])[0];
    $featured = resolveBlocks([['type' => 'news', 'source' => ['mode' => 'dynamic', 'filters' => ['featured' => true, 'status' => 'draft', 'id' => '1 OR 1=1']]]])[0];

    expect(array_column($byCategory['items'], 'title'))->toBe(['Tagged'])
        ->and(array_column($featured['items'], 'title'))->toBe(['Featured']);
});

it('rejects unknown categories and caps the limit', function () {
    expect(fn () => resolveBlocks([['type' => 'news', 'source' => ['mode' => 'dynamic', 'filters' => ['category' => 999999]]]]))
        ->toThrow(ValidationException::class);

    $clean = app(BlockTreeValidator::class)->validate([['type' => 'news', 'source' => ['mode' => 'dynamic', 'limit' => 500]]]);
    expect($clean[0]['source']['limit'])->toBe(24);
});

it('renders hand-entered items in the same item shape (static mode)', function () {
    $block = resolveBlocks([['type' => 'news', 'source' => ['mode' => 'static'], 'content' => ['items' => [
        ['title' => 'Manual item', 'excerpt' => 'Typed by hand', 'link' => ['type' => 'url', 'url' => 'https://example.org/story']],
    ]]]])[0];

    expect($block['items'][0])->toMatchArray(['title' => 'Manual item', 'url' => 'https://example.org/story', 'external' => true])
        ->and($block['items'][0]['link']['external'])->toBeTrue();
});

it('does not allow a source mode the block does not support', function () {
    expect(fn () => resolveBlocks([['type' => 'heading', 'content' => ['text' => 'x'], 'source' => ['mode' => 'dynamic']]]))
        ->toThrow(ValidationException::class);
});

it('serves external items through a registered provider, never a raw URL', function () {
    app(SourceRegistry::class)->registerExternal(new class implements ExternalProvider
    {
        public function key(): string
        {
            return 'demo';
        }

        public function hasSource(string $source): bool
        {
            return $source === 'partner-feed';
        }

        public function items(string $source, int $limit): array
        {
            return [['key' => 'demo:1', 'kind' => 'external', 'title' => 'From the partner', 'url' => 'https://partner.example/1', 'external' => true, 'excerpt' => null, 'image' => null, 'date' => null, 'meta' => []]];
        }
    });

    $type = new class extends BlockType
    {
        public function slug(): string
        {
            return 'demo-feed';
        }

        public function label(): string
        {
            return 'Demo feed';
        }

        public function category(): string
        {
            return 'external';
        }

        public function icon(): string
        {
            return 'bi-rss';
        }

        public function sourceModes(): array
        {
            return ['external'];
        }
    };
    app(BlockRegistry::class)->register($type);
    app(BlockRegistry::class)->sync();

    $block = resolveBlocks([['type' => 'demo-feed', 'source' => ['mode' => 'external', 'provider' => 'demo', 'source' => 'partner-feed', 'limit' => 3]]])[0];
    expect($block['items'][0]['title'])->toBe('From the partner');

    expect(fn () => resolveBlocks([['type' => 'demo-feed', 'source' => ['mode' => 'external', 'provider' => 'demo', 'source' => 'http://169.254.169.254/latest']]]))
        ->toThrow(ValidationException::class);
});

it('refreshes cached page payloads when news is published', function () {
    $editor = userWithRole('editor');
    $page = livePage(['title' => 'With news']);
    app(PageService::class)->update($editor, $page->fresh(), ['blocks' => [['type' => 'news', 'source' => ['mode' => 'dynamic']]]], $page->fresh()->lock_version);
    app(PublishingService::class)->transition($page->fresh(), WorkflowAction::Publish, $editor);

    $this->getJson('/api/v1/pages/with-news')->assertJsonCount(0, 'data.blocks.0.items');

    $service = app(NewsService::class);
    $item = $service->create($editor, ['title' => 'Breaking story']);
    $service->publish($editor, $item);

    $this->getJson('/api/v1/pages/with-news')->assertJsonPath('data.blocks.0.items.0.title', 'Breaking story');
});
