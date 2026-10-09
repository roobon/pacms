<?php

use App\Cms\Blocks\BlockTreeRepository;
use App\Enums\WorkflowAction;
use App\Models\BlockTemplate;
use App\Models\GlobalBlock;
use App\Models\MenuItem;
use App\Models\Page;
use App\Services\Blocks\BlockTemplateService;
use App\Services\Navigation\MenuService;
use App\Services\Publishing\PublishingService;
use App\Services\Settings\SettingsService;
use App\Services\Starter\StarterKitService;

it('installs every template and global block of the kit through the import pipeline', function () {
    $admin = userWithRole('super-admin');
    $manifest = app(StarterKitService::class)->manifest();

    app(StarterKitService::class)->install($admin);
    $this->artisan('pacms:starter')->assertSuccessful();

    expect(GlobalBlock::query()->whereIn('slug', array_keys($manifest['globals']))->count())->toBe(count($manifest['globals']))
        ->and(GlobalBlock::query()->where('slug', 'call-to-action')->value('status'))->toBe('published')
        ->and(BlockTemplate::query()->where('is_system', true)->count())->toBe(count($manifest['templates']))
        ->and(BlockTemplate::query()->where('scope', 'page')->count())->toBe(9)
        ->and(Page::query()->count())->toBe(0);
});

it('adds only what is missing on a second run, and restores shipped templates on request', function () {
    $admin = userWithRole('super-admin');
    $this->artisan('pacms:starter')->assertSuccessful();
    $count = BlockTemplate::query()->count();

    // An editor changes a shipped template and deletes another.
    $hero = BlockTemplate::query()->where('slug', 'kit-hero-centred')->firstOrFail();
    app(BlockTemplateService::class)->update($admin, $hero, ['name' => 'Our hero', 'blocks' => [['type' => 'heading', 'content' => ['text' => 'Changed', 'level' => '2']]]], $hero->lock_version);
    BlockTemplate::query()->where('slug', 'kit-faq')->firstOrFail()->delete();

    $this->artisan('pacms:starter')->assertSuccessful();
    expect(BlockTemplate::query()->count())->toBe($count - 1)
        ->and($hero->fresh()->name)->toBe('Our hero');

    $this->artisan('pacms:starter', ['--restore' => 'kit-hero-centred'])->assertSuccessful();
    expect($hero->fresh()->name)->toBe('Hero: centred')
        ->and(app(BlockTreeRepository::class)->load($hero->fresh())[0]['type'])->toBe('hero');

    $this->artisan('pacms:starter', ['--restore' => 'all'])->assertSuccessful();
    expect(BlockTemplate::query()->count())->toBe($count);
    $this->artisan('pacms:starter', ['--restore' => 'nope'])->assertFailed();
});

it('creates draft pages from the page templates with --pages, never over existing pages', function () {
    $admin = userWithRole('super-admin');
    makePage($admin, ['title' => 'About', 'slug' => 'about']);

    $this->artisan('pacms:starter', ['--pages' => true])->assertSuccessful();

    $home = Page::query()->where('slug', 'home')->firstOrFail();
    expect($home->status->value)->toBe('draft')
        ->and(Page::query()->where('slug', 'about')->value('title'))->toBe('About')
        ->and(Page::query()->whereIn('slug', ['contact', 'get-involved'])->count())->toBe(2)
        ->and(app(SettingsService::class)->get('site', 'homepage_page_id'))->toBe($home->id);

    $types = collect(app(BlockTreeRepository::class)->load($home))->pluck('type')->all();
    expect($types[0])->toBe('hero')->and(end($types))->toBe('section');

    // Published, the page renders with the shared call to action and live collections.
    app(PublishingService::class)->transition($home->fresh(), WorkflowAction::Publish, $admin);
    $blocks = $this->getJson('/api/v1/resolve?path=%2F')->assertJsonPath('kind', 'page')->json('data.blocks');
    expect(collect($blocks)->last()['children'][0]['type'])->toBe('global-ref');
});

it('lets a new page start from a page template', function () {
    $admin = userWithRole('super-admin');
    $this->artisan('pacms:starter')->assertSuccessful();
    $template = BlockTemplate::query()->where('slug', 'kit-page-contact')->firstOrFail();

    $this->actingAs($admin)->get(route('admin.pages.create'))->assertOk()->assertSee('Start from')->assertSee('Contact');
    $this->actingAs($admin)->post(route('admin.pages.store'), ['title' => 'Reach us', 'template' => 'default', 'start_template_id' => $template->id])->assertSessionHasNoErrors();

    $page = Page::query()->where('slug', 'reach-us')->firstOrFail();
    $blocks = app(BlockTreeRepository::class)->load($page);
    $original = app(BlockTreeRepository::class)->load($template);
    expect(count($blocks))->toBe(count($original))
        ->and($blocks[0]['uuid'])->not->toBe($original[0]['uuid']);

    // Section templates are not offered as a start.
    $section = BlockTemplate::query()->where('slug', 'kit-faq')->firstOrFail();
    $this->actingAs($admin)->post(route('admin.pages.store'), ['title' => 'Other', 'template' => 'default', 'start_template_id' => $section->id])->assertSessionHasErrors('start_template_id');
});

it('gives a new site its menus, header and footer, without touching ones already chosen', function () {
    $admin = userWithRole('super-admin');
    $about = livePage(['title' => 'About us', 'slug' => 'about']);
    $home = livePage(['title' => 'Home', 'slug' => 'home']);
    makePage($admin, ['title' => 'Draft', 'slug' => 'draft']);
    app(SettingsService::class)->set('site', ['homepage_page_id' => $home->id]);

    $this->artisan('pacms:starter')->assertSuccessful();

    // Menus: the home page first, then the other published top-level pages.
    $main = app(MenuService::class)->resolve('main');
    expect(array_column($main, 'url'))->toBe(['/', '/about'])
        ->and(app(MenuService::class)->resolve('footer'))->toHaveCount(2);

    // The kit's header and footer are now the site's, and show the logo, menu and copyright.
    $chrome = $this->getJson('/api/v1/site')->json('data.chrome');
    $types = collect($chrome['header'][0]['children'][0]['children'][0]['children'])->flatMap(fn ($column) => array_column($column['children'], 'type'))->all();
    expect($types)->toBe(['site-logo', 'menu', 'account-link'])
        ->and($chrome['footer'])->toHaveCount(1);

    // A second run leaves menus with items and a chosen header alone.
    $other = GlobalBlock::query()->forceCreate(['name' => 'Mine', 'slug' => 'mine', 'kind' => 'header']);
    app(SettingsService::class)->set('navigation', ['header_global_block_id' => $other->id]);
    MenuItem::query()->where('label', null)->first()->update(['label' => 'Start']);
    $this->artisan('pacms:starter')->assertSuccessful();
    expect(app(SettingsService::class)->get('navigation', 'header_global_block_id'))->toBe($other->id)
        ->and(MenuItem::query()->where('label', 'Start')->exists())->toBeTrue();
});
