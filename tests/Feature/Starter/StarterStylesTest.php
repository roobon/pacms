<?php

use App\Cms\Blocks\BlockTreeRepository;
use App\Models\BlockTemplate;
use App\Models\GlobalBlock;
use App\Models\Page;
use App\Services\Settings\SettingsService;
use App\Services\Starter\StarterKitService;

/** Phase 11S: five design styles with headers, footers and Home, About, Contact and Gallery pages. */
it('ships five designs for each area, each with a picture', function () {
    userWithRole('super-admin');
    $manifest = app(StarterKitService::class)->manifest();
    $this->artisan('pacms:starter')->assertSuccessful();

    expect(array_keys($manifest['styles']))->toBe(['classic', 'bold', 'editorial', 'impact', 'minimal', 'institutional']);
    // Six styles; Home pages also have "Home: Programmes", which belongs to no style.
    foreach (['Home pages' => 7, 'About pages' => 6, 'Contact pages' => 6, 'Gallery pages' => 6, 'Institutional' => 5] as $category => $count) {
        expect(BlockTemplate::query()->where('category', $category)->count())->toBe($count, $category);
    }
    foreach (['header', 'footer'] as $kind) {
        expect(GlobalBlock::query()->where('kind', $kind)->whereNotNull('published_revision_id')->count())->toBe(6, $kind);
    }

    // Every shipped page design, header and footer has a thumbnail.
    $slugs = [...BlockTemplate::query()->where('scope', 'page')->orWhere('category', 'Institutional')->pluck('slug')->all(), ...GlobalBlock::query()->whereIn('kind', ['header', 'footer'])->pluck('slug')->all()];
    foreach ($slugs as $slug) {
        expect(StarterKitService::thumbnail($slug))->toEndWith("/images/starter-kit/{$slug}.webp");
    }
    expect(StarterKitService::thumbnail('made-on-the-site'))->toBeNull()
        ->and(StarterKitService::thumbnail('../.env'))->toBeNull()
        ->and(StarterKitService::thumbnail(null))->toBeNull();
});

it('builds the pages and chooses the header and footer of a style', function () {
    userWithRole('super-admin');
    $this->artisan('pacms:starter', ['--pages' => true, '--style' => 'bold'])->assertSuccessful();

    $settings = app(SettingsService::class);
    expect(GlobalBlock::query()->find($settings->get('navigation', 'header_global_block_id'))->slug)->toBe('header-bold')
        ->and(GlobalBlock::query()->find($settings->get('navigation', 'footer_global_block_id'))->slug)->toBe('footer-bold');

    // The Bold home page starts with the slider; Get involved has one design for every style.
    $home = Page::query()->where('slug', 'home')->firstOrFail();
    expect(app(BlockTreeRepository::class)->load($home)[0]['type'])->toBe('slider')
        ->and(Page::query()->where('slug', 'get-involved')->exists())->toBeTrue();

    $this->artisan('pacms:starter', ['--style' => 'shiny'])->assertFailed();
});

it('builds the Institutional pages with its header and footer', function () {
    userWithRole('super-admin');
    $this->artisan('pacms:starter', ['--pages' => true, '--style' => 'institutional'])->assertSuccessful();

    $settings = app(SettingsService::class);
    expect(GlobalBlock::query()->find($settings->get('navigation', 'header_global_block_id'))->slug)->toBe('header-institutional')
        ->and(GlobalBlock::query()->find($settings->get('navigation', 'footer_global_block_id'))->slug)->toBe('footer-institutional');
    $about = app(BlockTreeRepository::class)->load(Page::query()->where('slug', 'about')->firstOrFail());
    expect($about[1]['children'][0]['content']['text'])->toBe('About us');
});

it('moves shipped templates into their area group without changing the design', function () {
    $admin = userWithRole('super-admin');
    $this->artisan('pacms:starter')->assertSuccessful();
    // As installed before 11S: the classic Home template in "Pages", with a name the site chose.
    $home = BlockTemplate::query()->where('slug', 'kit-page-home')->firstOrFail();
    $home->forceFill(['category' => 'Pages', 'name' => 'Our home'])->save();
    $blocks = app(BlockTreeRepository::class)->load($home);

    $this->artisan('pacms:starter')->assertSuccessful();

    expect($home->fresh()->category)->toBe('Home pages')
        ->and($home->fresh()->name)->toBe('Our home')
        ->and(app(BlockTreeRepository::class)->load($home->fresh()))->toEqual($blocks);
});

it('shows the designs with pictures where they are chosen', function () {
    $admin = userWithRole('super-admin');
    $this->artisan('pacms:starter')->assertSuccessful();

    $this->actingAs($admin)->get(route('admin.settings.navigation'))->assertOk()
        ->assertSee('pa-design-card', false)
        ->assertSee('images/starter-kit/header-bold.webp', false)
        ->assertSee('Footer: Minimal');

    $this->actingAs($admin)->get(route('admin.pages.create'))->assertOk()
        ->assertSee('See the designs')
        ->assertSee('<optgroup label="Home pages">', false)
        ->assertSee('images/starter-kit/kit-page-gallery-impact.webp', false);

    $templates = $this->actingAs($admin)->getJson(route('admin.api.templates.index'))->assertOk()->json('data');
    $bold = collect($templates)->firstWhere('name', 'Home: Bold');
    expect($bold['thumbnail'])->toEndWith('/images/starter-kit/kit-page-home-bold.webp')
        ->and($bold['category'])->toBe('Home pages');
});
