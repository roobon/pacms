<?php

use App\Cms\Blocks\BlockPayloadResolver;
use App\Cms\Blocks\BlockTreeValidator;
use App\Models\ExternalItem;
use App\Models\ExternalProviderAccount;
use App\Models\ExternalSource;
use App\Models\Media;
use App\Services\Cache\CacheVersions;
use App\Services\External\ExternalSyncService;
use App\Services\Settings\SettingsService;
use App\Support\Http\SafeHttpClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

const PAGE_ID = '1234567890';
const PAGE_TOKEN = 'EAAG-secret-page-token-1234567890';

/**
 * @param  array<string, mixed>  $extra  more fakes, matched first
 */
function fakeFacebook(array $posts, mixed $postsResponse = null, array $extra = []): void
{
    app()->instance(SafeHttpClient::class, new SafeHttpClient(fn () => ['93.184.216.34']));
    Http::fake($extra + [
        'https://graph.facebook.com/v26.0/'.PAGE_ID.'/published_posts*' => $postsResponse ?? Http::response(['data' => $posts]),
        'https://graph.facebook.com/v26.0/'.PAGE_ID.'?*' => Http::response(['id' => PAGE_ID, 'name' => 'Probha Aurora', 'link' => 'https://www.facebook.com/probhaaurora']),
        'https://scontent.example-fbcdn.net/*' => Http::response((string) file_get_contents(fakeJpeg('post.jpg', 800, 800)->getRealPath()), 200, ['Content-Type' => 'image/jpeg']),
        '*' => Http::response('', 404),
    ]);
}

function facebookPosts(): array
{
    return [
        ['id' => PAGE_ID.'_1', 'message' => "Planting day!\nStudents planted 1,000 seedlings.", 'created_time' => '2026-10-08T08:00:00+0000', 'permalink_url' => 'https://www.facebook.com/probhaaurora/posts/1', 'full_picture' => 'https://scontent.example-fbcdn.net/v/1.jpg?oh=signed', 'is_published' => true],
        ['id' => PAGE_ID.'_2', 'message' => 'Draft we did not publish', 'created_time' => '2026-10-09T08:00:00+0000', 'permalink_url' => 'https://www.facebook.com/probhaaurora/posts/2', 'is_published' => false],
        ['id' => PAGE_ID.'_3', 'message' => '<script>alert(1)</script> Clean-up on Saturday', 'created_time' => '2026-10-07T08:00:00+0000', 'permalink_url' => 'javascript:alert(1)', 'is_published' => true],
    ];
}

beforeEach(function () {
    Storage::fake((string) config('pacms.media.disk'));
});

it('connects the Page with a pasted token, stores it encrypted and reads published posts', function () {
    fakeFacebook(facebookPosts());
    $admin = userWithRole('administrator');

    $this->actingAs(userWithRole('editor'))->get(route('admin.facebook.show'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.facebook.show'))->assertOk()->assertSee('Paste a Page access token');

    $this->actingAs($admin)->post(route('admin.facebook.manual'), ['page_id' => PAGE_ID, 'page_token' => PAGE_TOKEN])->assertRedirect()->assertSessionHas('success');

    $account = ExternalProviderAccount::query()->firstOrFail();
    expect($account->credentials)->toBe(['page_id' => PAGE_ID, 'page_token' => PAGE_TOKEN])
        ->and(DB::table('external_provider_accounts')->value('credentials'))->not->toContain(PAGE_TOKEN);

    $source = ExternalSource::query()->firstOrFail();
    expect($source)->toMatchArray(['provider' => 'facebook', 'slug' => 'facebook-probha-aurora', 'last_status' => 'ok'])
        ->and($source->config['page_url'])->toBe('https://www.facebook.com/probhaaurora');

    // Published posts only; text escaped; unsafe links dropped.
    $items = ExternalItem::query()->orderBy('published_at')->get();
    expect($items->pluck('external_id')->all())->toBe([PAGE_ID.'_3', PAGE_ID.'_1'])
        ->and($items[0]->link)->toBeNull()
        ->and($items[0]->description)->not->toContain('<script>')
        ->and($items[1]->title)->toBe('Planting day!')
        ->and($items[1]->description)->toBe("<p>Planting day!<br>\nStudents planted 1,000 seedlings.</p>");

    // The image is a copy in the Media Library ("Facebook cache"), not Facebook's expiring address.
    $media = Media::query()->findOrFail($items[1]->image_media_id);
    expect($media->terms()->pluck('slug')->all())->toBe(['facebook-cache']);

    // The token never shows in the admin.
    $this->actingAs($admin)->get(route('admin.external-sources.edit', $source))->assertOk()->assertDontSee(PAGE_TOKEN);
    // Saving the source's settings keeps the Page.
    $this->actingAs($admin)->put(route('admin.external-sources.update', $source), ['provider' => 'facebook', 'name' => 'Our Facebook', 'sync_interval_minutes' => 60, 'max_items' => 30, 'enabled' => '1'])->assertSessionHasNoErrors();
    expect($source->fresh()->config['page_id'])->toBe(PAGE_ID);
});

it('refuses a token Facebook does not accept, without storing anything', function () {
    app()->instance(SafeHttpClient::class, new SafeHttpClient(fn () => ['93.184.216.34']));
    Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid OAuth access token.']], 400)]);

    $this->actingAs(userWithRole('administrator'))->post(route('admin.facebook.manual'), ['page_id' => PAGE_ID, 'page_token' => PAGE_TOKEN])
        ->assertSessionHas('warning', fn ($message) => str_contains($message, 'access token') && ! str_contains($message, PAGE_TOKEN));
    expect(ExternalProviderAccount::query()->count())->toBe(0)->and(ExternalSource::query()->count())->toBe(0);
});

it('keeps showing stored posts when the token stops working', function () {
    fakeFacebook(facebookPosts(), Http::sequence()->push(['data' => facebookPosts()])->push(['error' => ['message' => 'Session has expired']], 400));
    $this->actingAs(userWithRole('administrator'))->post(route('admin.facebook.manual'), ['page_id' => PAGE_ID, 'page_token' => PAGE_TOKEN]);
    $source = ExternalSource::query()->firstOrFail();

    $log = app(ExternalSyncService::class)->sync($source->fresh());
    expect($log->status)->toBe('error')
        ->and($source->fresh()->last_error)->toContain('Reconnect the Page')->not->toContain(PAGE_TOKEN)
        ->and(ExternalItem::query()->count())->toBe(2);
});

it('connects with Facebook (OAuth) and lets the admin choose the Page', function () {
    config(['services.meta.app_id' => '42', 'services.meta.app_secret' => 'app-secret']);
    fakeFacebook(facebookPosts(), null, [
        'https://graph.facebook.com/v26.0/oauth/access_token*' => Http::sequence()->push(['access_token' => 'short-user'])->push(['access_token' => 'long-user']),
        'https://graph.facebook.com/v26.0/me/accounts*' => Http::response(['data' => [['id' => PAGE_ID, 'name' => 'Probha Aurora', 'access_token' => PAGE_TOKEN]]]),
        'https://graph.facebook.com/v26.0/debug_token*' => Http::response(['data' => ['expires_at' => 0]]),
    ]);
    $admin = userWithRole('administrator');

    $redirect = $this->actingAs($admin)->get(route('admin.facebook.start'))->assertRedirect()->headers->get('Location');
    expect($redirect)->toStartWith('https://www.facebook.com/v26.0/dialog/oauth')->toContain('pages_read_user_content');
    parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);

    // A forged answer is refused.
    $this->actingAs($admin)->get(route('admin.facebook.callback', ['code' => 'x', 'state' => 'forged']))->assertSessionHas('warning');

    $this->actingAs($admin)->get(route('admin.facebook.start'));
    parse_str((string) parse_url($this->actingAs($admin)->get(route('admin.facebook.start'))->headers->get('Location'), PHP_URL_QUERY), $query);
    $this->actingAs($admin)->get(route('admin.facebook.callback', ['code' => 'the-code', 'state' => $query['state']]))->assertRedirect(route('admin.facebook.show'));
    $this->actingAs($admin)->get(route('admin.facebook.show'))->assertSee('Choose the Page')->assertDontSee(PAGE_TOKEN);

    $this->actingAs($admin)->post(route('admin.facebook.choose'), ['page_id' => PAGE_ID])->assertSessionHas('success');
    expect(ExternalProviderAccount::query()->firstOrFail()->credentials['page_token'])->toBe(PAGE_TOKEN)
        ->and(ExternalProviderAccount::query()->firstOrFail()->token_expires_at)->toBeNull();
});

it('shows posts in a Facebook block, or a follow link when there are none', function () {
    fakeFacebook(facebookPosts());
    $admin = userWithRole('super-admin');
    $this->actingAs($admin)->post(route('admin.facebook.manual'), ['page_id' => PAGE_ID, 'page_token' => PAGE_TOKEN]);
    $resolve = fn () => app(BlockPayloadResolver::class)->resolve(app(BlockTreeValidator::class)->validate([['type' => 'facebook-feed', 'content' => ['when_empty' => 'follow'], 'source' => ['mode' => 'external', 'provider' => 'facebook', 'source' => 'facebook-probha-aurora', 'limit' => 3]]], $admin))[0];

    $block = $resolve();
    expect($block['items'][0]['title'])->toBe('Planting day!')
        ->and($block['items'][0]['image']['src'])->toContain('/storage/')
        ->and($block['items'][0]['meta']['source'])->toBe('Probha Aurora (Facebook)');

    // No posts: the follow link uses the Facebook address from Header & footer.
    app(SettingsService::class)->set('navigation', ['social' => ['facebook' => 'https://www.facebook.com/probhaaurora']]);
    app(ExternalSyncService::class)->clear(ExternalSource::query()->firstOrFail());
    app(CacheVersions::class)->bump('external');
    $empty = $resolve();
    expect($empty['items'])->toBe([])->and($empty['data']['follow_url'])->toBe('https://www.facebook.com/probhaaurora');
    // Clearing removed the cached images too.
    expect(Media::query()->count())->toBe(0);
});

it('warns a week before a token expires', function () {
    fakeFacebook(facebookPosts());
    $admin = userWithRole('administrator');
    $this->actingAs($admin)->post(route('admin.facebook.manual'), ['page_id' => PAGE_ID, 'page_token' => PAGE_TOKEN]);
    ExternalProviderAccount::query()->update(['token_expires_at' => now()->addDays(3)]);

    $this->actingAs($admin)->get(route('admin.external-sources.index'))->assertSee('expires')->assertSee('Connect the Page again');
});

it('lets a Facebook block be placed before the Page is connected', function () {
    $admin = userWithRole('super-admin');
    app(SettingsService::class)->set('navigation', ['social' => ['facebook' => 'https://www.facebook.com/probhaaurora']]);

    $clean = app(BlockTreeValidator::class)->validate([['type' => 'facebook-feed', 'content' => ['when_empty' => 'follow'], 'source' => ['mode' => 'external', 'provider' => 'facebook', 'source' => '']]], $admin);
    $block = app(BlockPayloadResolver::class)->resolve($clean)[0];

    expect($block['items'])->toBe([])->and($block['data']['follow_url'])->toBe('https://www.facebook.com/probhaaurora');
});
