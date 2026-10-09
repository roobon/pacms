<?php

use App\Cms\Blocks\BlockTreeValidator;
use App\Cms\External\FeedException;
use App\Cms\External\FeedParser;
use App\Enums\ContentStatus;
use App\Jobs\SyncExternalSource;
use App\Models\ExternalItem;
use App\Models\ExternalSource;
use App\Models\ExternalSyncLog;
use App\Models\News;
use App\Models\Term;
use App\Services\Cache\CacheVersions;
use App\Services\External\ExternalSyncService;
use App\Support\Http\SafeHttpClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function feedFixture(string $name): string
{
    return (string) file_get_contents(base_path("tests/Fixtures/feeds/{$name}"));
}

/** Public DNS for every host except internal.example (a private address). */
function fakeFeedNetwork(array $responses): void
{
    app()->instance(SafeHttpClient::class, new SafeHttpClient(fn (string $host) => $host === 'internal.example' ? ['10.0.0.5'] : ['93.184.216.34']));
    Http::fake($responses + ['*' => Http::response('', 404)]);
}

function feedSource(array $attributes = []): ExternalSource
{
    $source = new ExternalSource(['name' => $attributes['name'] ?? 'Partner news', 'config' => ['feed_url' => $attributes['url'] ?? 'https://partner.example.org/feed.json'], 'max_items' => $attributes['max_items'] ?? 50, 'sync_interval_minutes' => 60]);
    $source->provider = 'feed';
    $source->slug = $attributes['slug'] ?? Str::slug($source->name);
    $source->status = $attributes['status'] ?? 'enabled';
    $source->save();

    return $source;
}

it('reads JSON Feed, RSS and Atom (YouTube), cleaning text and links', function () {
    $parser = app(FeedParser::class);

    $json = $parser->parse(feedFixture('feed.json'), 'https://partner.example.org/feed.json');
    expect($json['format'])->toBe('json')
        ->and($json['items'][0])->toMatchArray(['external_id' => 'news:7', 'title' => 'Seven', 'excerpt' => 'A summary.', 'category' => 'Schools', 'author' => 'Nadia', 'image_url' => 'https://partner.example.org/7.jpg'])
        ->and($json['items'][0]['description'])->not->toContain('javascript')
        // A date far in the future is a feed error: it would stay on top for ever.
        ->and($json['items'][1]['published_at']->isFuture())->toBeFalse();

    $rss = $parser->parse(feedFixture('rss.xml'), 'https://news.example.org/feed');
    expect($rss['format'])->toBe('rss')
        ->and($rss['items'][0])->toMatchArray(['external_id' => 'story-1', 'title' => 'Mangroves & climate', 'link' => 'https://news.example.org/stories/mangroves', 'image_url' => 'https://news.example.org/img/mangroves.jpg', 'category' => 'Climate'])
        ->and($rss['items'][0]['description'])->toBe('<p>Coastal forests <strong>protect</strong> villages.</p>')
        ->and($rss['items'][1]['link'])->toBeNull();

    $atom = $parser->parse(feedFixture('atom.xml'), 'https://www.youtube.com/feeds/videos.xml?channel_id=UC123');
    expect($atom['format'])->toBe('atom')
        ->and($atom['items'][0])->toMatchArray(['title' => 'Planting day', 'link' => 'https://www.youtube.com/watch?v=abc123', 'image_url' => 'https://i.ytimg.com/vi/abc123/hqdefault.jpg', 'excerpt' => 'Students plant 1,000 seedlings.']);
});

it('refuses unsafe XML and documents that are not feeds', function () {
    $parser = app(FeedParser::class);
    expect(fn () => $parser->parse(feedFixture('xxe.xml'), 'https://x.example/'))->toThrow(FeedException::class, 'document type or entities');
    $laughs = '<?xml version="1.0"?><!DOCTYPE lolz [<!ENTITY lol "lol"><!ENTITY lol2 "&lol;&lol;&lol;">]><rss><channel><title>&lol2;</title></channel></rss>';
    expect(fn () => $parser->parse($laughs, 'https://x.example/'))->toThrow(FeedException::class)
        ->and(fn () => $parser->parse('<html><body>Hello</body></html>', 'https://x.example/'))->toThrow(FeedException::class)
        ->and(fn () => $parser->parse('{"title": "not a feed"}', 'https://x.example/'))->toThrow(FeedException::class, 'not a JSON Feed')
        ->and(fn () => $parser->parse('Hello', 'https://x.example/'))->toThrow(FeedException::class);
});

it('stores items, updates them on the next sync and keeps only the newest', function () {
    fakeFeedNetwork(['https://news.example.org/*' => Http::response(feedFixture('rss.xml'), 200, ['Content-Type' => 'application/rss+xml'])]);
    $source = feedSource(['url' => 'https://news.example.org/feed', 'max_items' => 1]);

    $log = app(ExternalSyncService::class)->sync($source);
    expect($log->status)->toBe('ok')
        ->and($log->items_fetched)->toBe(2)
        ->and(ExternalItem::query()->pluck('external_id')->all())->toBe(['story-1'])
        ->and($source->fresh())->toMatchArray(['last_status' => 'ok', 'format' => 'rss', 'source_website' => 'https://news.example.org/', 'consecutive_failures' => 0])
        ->and($source->fresh()->next_sync_at->isFuture())->toBeTrue();

    expect(app(ExternalSyncService::class)->sync($source->fresh())->items_updated)->toBe(1)
        ->and(ExternalItem::query()->count())->toBe(1);
});

it('keeps showing the stored items when a sync fails, and waits longer each time', function () {
    fakeFeedNetwork(['https://partner.example.org/feed.json' => Http::sequence()->push(feedFixture('feed.json'), 200)->push('', 500)->push('', 500)]);
    $source = feedSource();
    app(ExternalSyncService::class)->sync($source);
    expect(ExternalItem::query()->count())->toBe(2);

    $this->travel(2)->hours();
    $log = app(ExternalSyncService::class)->sync($source->fresh());
    $first = $source->fresh();
    expect($log->status)->toBe('error')
        ->and($first->last_error)->toContain('HTTP 500')
        ->and($first->consecutive_failures)->toBe(1)
        ->and((int) round(now()->diffInMinutes($first->next_sync_at)))->toBe(120)
        ->and(ExternalItem::query()->count())->toBe(2); // stale-while-error

    app(ExternalSyncService::class)->sync($first);
    expect((int) round(now()->diffInMinutes($source->fresh()->next_sync_at)))->toBe(240)
        ->and(ExternalSyncLog::query()->where('status', 'error')->count())->toBe(2);
});

it('never reads internal addresses', function () {
    fakeFeedNetwork([]);
    $source = feedSource(['url' => 'https://internal.example/feed.json']);
    $log = app(ExternalSyncService::class)->sync($source);

    expect($log->status)->toBe('error')
        ->and($source->fresh()->last_error)->not->toBeNull()
        ->and(ExternalItem::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('queues sources that are due, and only those', function () {
    Queue::fake();
    $due = feedSource(['name' => 'Due']);
    $later = feedSource(['name' => 'Later']);
    $later->forceFill(['next_sync_at' => now()->addHour()])->save();
    feedSource(['name' => 'Off', 'status' => 'disabled']);

    $this->artisan('pacms:external:sync')->assertSuccessful();

    Queue::assertPushed(SyncExternalSource::class, 1);
    Queue::assertPushed(SyncExternalSource::class, fn ($job) => $job->sourceId === $due->id);
    expect($due->fresh()->next_sync_at->isFuture())->toBeTrue();
});

it('lets administrators manage sources and editors sync them', function () {
    fakeFeedNetwork(['https://partner.example.org/*' => Http::response(feedFixture('feed.json'), 200, ['Content-Type' => 'application/feed+json'])]);
    $admin = userWithRole('administrator');
    $editor = userWithRole('editor');

    $this->actingAs(userWithRole('author', twoFactor: false))->get(route('admin.external-sources.index'))->assertForbidden();
    $this->actingAs($editor)->get(route('admin.external-sources.create'))->assertForbidden();

    // Test reads without storing anything.
    $this->actingAs($admin)->post(route('admin.external-sources.test'), ['provider' => 'feed', 'config' => ['feed_url' => 'https://partner.example.org/feed.json']])
        ->assertSessionHas('test', fn ($test) => $test['ok'] && $test['format'] === 'json');
    expect(ExternalSource::query()->count())->toBe(0);

    $this->actingAs($admin)->post(route('admin.external-sources.store'), [
        'provider' => 'feed', 'name' => 'Partner news', 'config' => ['feed_url' => 'https://partner.example.org/feed.json'],
        'sync_interval_minutes' => 60, 'max_items' => 20, 'enabled' => '1',
    ])->assertSessionHasNoErrors()->assertSessionHas('success');
    $source = ExternalSource::query()->firstOrFail();
    expect($source->slug)->toBe('partner-news')->and($source->items()->count())->toBe(2);

    $this->actingAs($admin)->post(route('admin.external-sources.store'), ['provider' => 'feed', 'name' => 'Bad', 'config' => ['feed_url' => 'javascript:alert(1)'], 'sync_interval_minutes' => 60, 'max_items' => 20])
        ->assertSessionHasErrors('config.feed_url');

    // Editors sync (at most 6 times an hour per source) but do not change sources.
    $this->actingAs($editor)->get(route('admin.external-sources.edit', $source))->assertOk()->assertSee('Sync now');
    $this->actingAs($editor)->put(route('admin.external-sources.update', $source), ['name' => 'X'])->assertForbidden();
    foreach (range(1, 6) as $i) {
        $this->actingAs($editor)->post(route('admin.external-sources.sync', $source))->assertSessionHas('success');
    }
    $this->actingAs($editor)->post(route('admin.external-sources.sync', $source))->assertSessionHas('warning');

    $this->actingAs($admin)->post(route('admin.external-sources.clear', $source))->assertSessionHas('success');
    expect($source->items()->count())->toBe(0);
    $this->actingAs($admin)->delete(route('admin.external-sources.destroy', $source))->assertRedirect(route('admin.external-sources.index'));
    expect(ExternalSource::query()->count())->toBe(0);
});

it('shows a source\'s items in a Feed block, refreshed after each sync', function () {
    fakeFeedNetwork(['https://partner.example.org/*' => Http::response(feedFixture('feed.json'), 200)]);
    $source = feedSource();
    app(ExternalSyncService::class)->sync($source);

    $page = livePage(['title' => 'Partners', 'slug' => 'partner-news', 'blocks' => [['type' => 'feed', 'content' => ['heading' => 'From partners'], 'source' => ['mode' => 'external', 'provider' => 'feed', 'source' => 'partner-news', 'limit' => 5]]]]);
    $block = $this->getJson('/api/v1/resolve?path=/partner-news')->json('data.blocks.0');
    expect($block['type'])->toBe('feed')
        // "Eight" has a date far in the future, read as "now": it comes first.
        ->and(array_column($block['items'], 'title'))->toBe(['Eight', 'Seven'])
        ->and($block['items'][1])->toMatchArray(['external' => true, 'url' => 'https://partner.example.org/news/seven', 'meta' => ['source' => 'Partner news', 'category' => 'Schools']]);

    // New items appear without republishing the page.
    ExternalItem::query()->where('external_id', 'news:8')->delete();
    app(ExternalSyncService::class)->clear($source);
    expect($this->getJson('/api/v1/resolve?path=/partner-news')->json('data.blocks.0.items'))->toBe([]);

    // A disabled source shows nothing; unknown sources and other blocks are refused.
    app(ExternalSyncService::class)->sync($source->fresh());
    $source->forceFill(['status' => 'disabled'])->save();
    app(CacheVersions::class)->bump('external');
    expect($this->getJson('/api/v1/resolve?path=/partner-news')->json('data.blocks.0.items'))->toBe([]);
    $admin = userWithRole('super-admin');
    expect(fn () => app(BlockTreeValidator::class)->validate([['type' => 'feed', 'source' => ['mode' => 'external', 'provider' => 'feed', 'source' => 'nope']]], $admin))->toThrow(ValidationException::class);
    expect(fn () => app(BlockTreeValidator::class)->validate([['type' => 'news', 'source' => ['mode' => 'external', 'provider' => 'feed', 'source' => 'partner-news']]], $admin))->toThrow(ValidationException::class);
    expect($page)->not->toBeNull();
});

it('publishes JSON Feed 1.1 with published items only', function () {
    $category = Term::query()->create(['taxonomy' => 'news_category', 'name' => 'Climate', 'slug' => 'climate']);
    $make = function (string $title, ContentStatus $status, ?DateTimeInterface $published) {
        $item = new News(['title' => $title, 'excerpt' => '<p>About '.$title.'</p>', 'body' => '<p>Body</p><script>x</script>']);
        $item->slug = Str::slug($title);
        $item->forceFill(['status' => $status, 'published_at' => $published])->save();

        return $item;
    };
    $live = $make('Live story', ContentStatus::Published, now()->subDay());
    $live->syncTerms('news_category', [$category->id]);
    $make('Older story', ContentStatus::Published, now()->subDays(3));
    $make('Draft story', ContentStatus::Draft, null);
    $make('Future story', ContentStatus::Published, now()->addDay());

    $response = $this->get('/feed.json')->assertOk()->assertHeader('Content-Type', 'application/feed+json; charset=UTF-8');
    $feed = $response->json();
    expect($feed['version'])->toBe('https://jsonfeed.org/version/1.1')
        ->and($feed['feed_url'])->toEndWith('/feed.json')
        ->and(array_column($feed['items'], 'title'))->toBe(['Live story', 'Older story'])
        ->and($feed['items'][0])->toMatchArray(['id' => 'news:'.$live->id, 'summary' => 'About Live story', 'content_html' => '<p>Body</p>', 'tags' => ['Climate'], '_pacms' => ['type' => 'news', 'slug' => 'live-story']])
        ->and($feed['items'][0]['url'])->toStartWith('http')->toEndWith('/news/live-story');

    $this->getJson('/feed/news.json?category=climate')->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('title', fn ($title) => str_contains($title, 'Climate'));
    $this->get('/feed/news.json?category=nope')->assertNotFound();
    $this->get('/feed/nothing.json')->assertNotFound();
    // Pages can find it.
    $this->get('/')->assertSee('application/feed+json', false);
});
