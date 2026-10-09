<?php

use App\Enums\ContentStatus;
use App\Enums\WorkflowAction;
use App\Models\GlobalBlock;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\News;
use App\Models\Term;
use App\Services\Blocks\GlobalBlockService;
use App\Services\Navigation\MenuService;
use App\Services\Pages\PageService;
use App\Services\Publishing\PublishingService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Str;

function publishedNews(string $title): News
{
    $item = new News(['title' => $title, 'excerpt' => 'Summary']);
    $item->slug = Str::slug($title);
    $item->forceFill(['status' => ContentStatus::Published, 'published_at' => now()->subDay()])->save();

    return $item;
}

function menuWith(array $items, string $name = 'Main'): Menu
{
    $admin = userWithRole('administrator');
    $menu = app(MenuService::class)->create($admin, ['name' => $name]);

    return app(MenuService::class)->saveTree($admin, $menu, $items, $menu->lock_version);
}

it('lets only people with "Manage menus" edit menus', function () {
    $this->actingAs(userWithRole('author', twoFactor: false))->get(route('admin.menus.index'))->assertForbidden();
    $editor = userWithRole('editor');
    $this->actingAs($editor)->post(route('admin.menus.store'), ['name' => 'Main menu'])->assertRedirect();
    $menu = Menu::query()->firstOrFail();
    expect($menu->slug)->toBe('main-menu');
    $this->actingAs($editor)->get(route('admin.menus.edit', $menu))->assertOk()->assertSee('data-menu-builder', false);
    $this->actingAs($editor)->getJson(route('admin.api.menus.tree', $menu))->assertOk()->assertJsonPath('data.items', []);
});

it('saves a nested menu of pages, content, categories, addresses and headings', function () {
    $admin = userWithRole('administrator');
    $about = livePage(['title' => 'About us', 'slug' => 'about']);
    $team = livePage(['title' => 'Team', 'slug' => 'team-page']);
    $news = publishedNews('Big news');
    $category = Term::query()->create(['taxonomy' => 'news_category', 'name' => 'Climate', 'slug' => 'climate']);
    $menu = app(MenuService::class)->create($admin, ['name' => 'Main']);

    $this->actingAs($admin)->putJson(route('admin.api.menus.tree.update', $menu), ['lock_version' => 0, 'items' => [
        ['type' => 'page', 'target' => ['entity' => 'pages', 'id' => $about->id], 'children' => [
            ['type' => 'page', 'label' => 'Our team', 'target' => ['entity' => 'pages', 'id' => $team->id]],
        ]],
        ['type' => 'group', 'label' => 'News', 'children' => [
            ['type' => 'content', 'target' => ['entity' => 'news', 'id' => $news->id], 'icon' => 'bi-star', 'new_tab' => true],
            ['type' => 'term', 'target' => ['id' => $category->id]],
        ]],
        ['type' => 'external_url', 'label' => 'Donate', 'url' => 'https://give.example.org', 'visibility' => 'guests', 'class' => 'btn-cta'],
        ['type' => 'custom_url', 'label' => 'Contact', 'url' => '/contact#form', 'visibility' => 'members'],
    ]])->assertOk()->assertJsonPath('data.lock_version', 1);

    $items = app(MenuService::class)->resolve('main');
    expect(array_column($items, 'label'))->toBe(['About us', 'News', 'Donate', 'Contact'])
        ->and($items[0]['url'])->toBe('/about')
        ->and($items[0]['children'][0])->toMatchArray(['label' => 'Our team', 'url' => '/team-page'])
        ->and($items[1]['url'])->toBeNull()
        ->and($items[1]['children'][0])->toMatchArray(['label' => 'Big news', 'url' => '/news/big-news', 'icon' => 'bi-star', 'new_tab' => true])
        ->and($items[1]['children'][1])->toMatchArray(['label' => 'Climate', 'url' => '/news?category=climate'])
        ->and($items[2])->toMatchArray(['external' => true, 'visibility' => 'guests', 'class' => 'btn-cta'])
        ->and($items[3]['visibility'])->toBe('members');
});

it('serves a menu through the public API', function () {
    menuWith([['type' => 'custom_url', 'label' => 'Contact', 'url' => '/contact', 'visibility' => 'members']]);

    $this->getJson('/api/v1/menus/main')->assertOk()
        ->assertJsonPath('data.items.0.label', 'Contact')
        ->assertJsonPath('data.items.0.visibility', 'members')
        ->assertHeader('Cache-Control', 'max-age=300, public')
        ->assertJsonMissingPath('data.items.0.type');
    $this->getJson('/api/v1/menus/nope')->assertNotFound();
});

it('refuses unsafe addresses, missing labels and menus deeper than four levels', function () {
    $admin = userWithRole('administrator');
    $menu = app(MenuService::class)->create($admin, ['name' => 'Main']);
    $save = fn (array $items, int $lock = 0) => $this->actingAs($admin)->putJson(route('admin.api.menus.tree.update', $menu), ['lock_version' => $lock, 'items' => $items]);

    $save([['type' => 'external_url', 'label' => 'X', 'url' => 'javascript:alert(1)']])->assertJsonValidationErrors('items.n0.url');
    $save([['type' => 'custom_url', 'label' => 'X', 'url' => '//evil.example']])->assertJsonValidationErrors('items.n0.url');
    $save([['type' => 'custom_url', 'url' => '/ok']])->assertJsonValidationErrors('items.n0.label');
    $save([['type' => 'page', 'target' => ['id' => 999]]])->assertJsonValidationErrors('items.n0.target');
    $save([['type' => 'custom_url', 'label' => 'X', 'url' => '/x', 'class' => 'a"b']])->assertJsonValidationErrors('items.n0.class');
    $deep = ['type' => 'group', 'label' => '5'];
    foreach (['4', '3', '2', '1'] as $level) {
        $deep = ['type' => 'group', 'label' => $level, 'children' => [$deep]];
    }
    $save([$deep])->assertJsonValidationErrors('items.n4');
    // Someone else saved in the meantime.
    $save([], 7)->assertJsonValidationErrors('menu');
    expect(MenuItem::query()->count())->toBe(0);
});

it('hides links to unpublished targets with their sub-items, and follows changed addresses', function () {
    $admin = userWithRole('administrator');
    $draft = makePage($admin, ['title' => 'Draft page', 'slug' => 'draft']);
    $live = livePage(['title' => 'Programmes', 'slug' => 'programmes']);
    menuWith([
        ['type' => 'page', 'target' => ['id' => $draft->id], 'children' => [['type' => 'custom_url', 'label' => 'Child', 'url' => '/child']]],
        ['type' => 'page', 'target' => ['id' => $live->id]],
        ['type' => 'group', 'label' => 'Empty heading'],
    ]);

    expect(array_column(app(MenuService::class)->resolve('main'), 'label'))->toBe(['Programmes']);

    // The builder still shows the hidden item, flagged.
    $tree = $this->actingAs($admin)->getJson(route('admin.api.menus.tree', Menu::query()->first()))->json('data.items');
    expect($tree[0]['public'])->toBeFalse()->and($tree[0]['target']['title'])->toBe('Draft page');

    // A moved page: the menu follows (no broken link).
    app(PageService::class)->update($admin, $live->fresh(), ['slug' => 'our-programmes'], $live->fresh()->lock_version);
    app(PublishingService::class)->transition($live->fresh(), WorkflowAction::Publish, $admin);
    expect(app(MenuService::class)->resolve('main')[0]['url'])->toBe('/our-programmes');

    // The home page links to "/".
    app(SettingsService::class)->set('site', ['homepage_page_id' => $live->id]);
    expect(app(MenuService::class)->resolve('main')[0]['url'])->toBe('/');
});

it('keeps item ids when a menu is saved again', function () {
    $menu = menuWith([['type' => 'custom_url', 'label' => 'A', 'url' => '/a'], ['type' => 'custom_url', 'label' => 'B', 'url' => '/b']]);
    $ids = MenuItem::query()->orderBy('position')->pluck('id')->all();

    $admin = userWithRole('administrator');
    app(MenuService::class)->saveTree($admin, $menu, [
        ['id' => $ids[1], 'type' => 'custom_url', 'label' => 'B', 'url' => '/b', 'children' => [['type' => 'custom_url', 'label' => 'C', 'url' => '/c']]],
    ], $menu->lock_version);

    expect(MenuItem::query()->pluck('id')->all())->toContain($ids[1])->not->toContain($ids[0])
        ->and(MenuItem::query()->where('label', 'C')->value('parent_id'))->toBe($ids[1]);
});

it('shows the chosen header and footer on the website, and lets a page choose another or none', function () {
    $admin = userWithRole('administrator');
    menuWith([['type' => 'custom_url', 'label' => 'Contact', 'url' => '/contact']]);
    $globals = app(GlobalBlockService::class);
    $header = $globals->create($admin, ['name' => 'Main header', 'kind' => 'header', 'blocks' => [
        ['type' => 'site-logo', 'content' => ['show_name' => true]],
        ['type' => 'menu', 'content' => ['menu' => 'main', 'style' => 'horizontal', 'aria_label' => 'Main']],
    ]]);
    $globals->publish($admin, $header);
    $landing = $globals->create($admin, ['name' => 'Landing header', 'kind' => 'header', 'blocks' => [['type' => 'site-logo']]]);
    $globals->publish($admin, $landing);
    $footer = $globals->create($admin, ['name' => 'Footer', 'kind' => 'footer', 'blocks' => [['type' => 'copyright', 'content' => ['text' => '© {{year}} {{site_name}}. All rights reserved.']], ['type' => 'social-links']]]);
    $globals->publish($admin, $footer);

    $this->actingAs($admin)->put(route('admin.settings.navigation.update'), [
        'header_global_block_id' => $header->id, 'footer_global_block_id' => $footer->id, 'sticky_header' => '1',
        'social' => ['facebook' => 'https://facebook.com/example', 'x' => ''], 'copyright' => '',
    ])->assertSessionHasNoErrors();
    app(SettingsService::class)->set('site', ['name' => 'Aurora']);

    $site = $this->getJson('/api/v1/site')->assertOk()->json('data.chrome');
    $headerBlocks = $site['header'][0]['children'];
    expect($headerBlocks[0]['data']['name'])->toBe('Aurora')
        ->and($headerBlocks[1]['data']['items'][0]['label'])->toBe('Contact')
        ->and($site['footer'][0]['children'][0]['data']['text'])->toBe('© '.now()->year.' Aurora. All rights reserved.')
        ->and($site['footer'][0]['children'][1]['data']['links'])->toBe([['network' => 'facebook', 'label' => 'Facebook', 'icon' => 'bi-facebook', 'url' => 'https://facebook.com/example']])
        ->and($site['sticky'])->toBeTrue();

    // A saved menu reaches the site header at once (no stale cache).
    $main = Menu::query()->where('slug', 'main')->firstOrFail();
    app(MenuService::class)->saveTree($admin, $main, [['type' => 'custom_url', 'label' => 'Donate', 'url' => '/donate']], $main->lock_version);
    expect($this->getJson('/api/v1/site')->json('data.chrome.header.0.children.1.data.items.0.label'))->toBe('Donate');

    // Pages: none, or another header.
    $plain = livePage(['title' => 'Landing', 'slug' => 'landing', 'header_mode' => 'none', 'footer_mode' => 'custom', 'footer_global_block_id' => $footer->id]);
    $this->getJson('/api/v1/resolve?path=/landing')->assertJsonPath('data.chrome.header', 'none')->assertJsonPath('data.chrome.footer.0.type', 'global-ref');
    livePage(['title' => 'Campaign', 'slug' => 'campaign', 'header_mode' => 'custom', 'header_global_block_id' => $landing->id]);
    $chrome = $this->getJson('/api/v1/resolve?path=/campaign')->json('data.chrome');
    expect($chrome['header'][0]['children'][0]['type'])->toBe('site-logo')->and($chrome)->not->toHaveKey('footer');
    // Pages that keep the site's header and footer say nothing.
    livePage(['title' => 'Plain', 'slug' => 'plain']);
    expect($this->getJson('/api/v1/resolve?path=/plain')->json('data.chrome'))->toBe([]);
});

it('validates the page header choice and the header & footer settings', function () {
    $admin = userWithRole('administrator');
    $sidebar = GlobalBlock::query()->forceCreate(['name' => 'Sidebar', 'slug' => 'sidebar', 'kind' => 'sidebar']);

    $this->actingAs($admin)->post(route('admin.pages.store'), ['title' => 'X', 'template' => 'default', 'header_mode' => 'custom', 'header_global_block_id' => $sidebar->id])->assertSessionHasErrors('header_global_block_id');
    $this->actingAs($admin)->post(route('admin.pages.store'), ['title' => 'X', 'template' => 'default', 'header_mode' => 'custom'])->assertSessionHasErrors('header_global_block_id');
    $this->actingAs($admin)->put(route('admin.settings.navigation.update'), ['social' => ['facebook' => 'javascript:alert(1)']])->assertSessionHasErrors('social.facebook');
    $this->actingAs($admin)->put(route('admin.settings.navigation.update'), ['footer_global_block_id' => $sidebar->id])->assertSessionHasErrors('footer_global_block_id');

    $this->actingAs($admin)->get(route('admin.pages.create'))->assertOk()->assertSee('Header')->assertSee('Site default');
    $this->actingAs($admin)->get(route('admin.settings.navigation'))->assertOk()->assertSee('Social profiles');
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertSee('Menus')->assertSee('Header &amp; footer', false);
});
