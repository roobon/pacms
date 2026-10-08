<?php

use App\Models\News;
use App\Models\Term;

function publishNews(News $news, $user = null): void
{
    test()->actingAs($user ?? userWithRole('editor'))
        ->post(route('admin.news.workflow', $news), ['action' => 'publish'])
        ->assertRedirect(route('admin.news.edit', $news));
}

it('lets an editor create and publish news, which then has a public page', function () {
    $editor = userWithRole('editor');

    $this->actingAs($editor)->post(route('admin.news.store'), ['title' => 'Schools plant 10,000 trees', 'excerpt' => 'A record year.'])
        ->assertRedirect();
    $news = News::firstOrFail();
    expect($news->slug)->toBe('schools-plant-10000-trees')
        ->and($news->status->value)->toBe('draft')
        ->and($news->revisions()->count())->toBe(1);

    $this->get('/news/'.$news->slug)->assertNotFound();

    publishNews($news, $editor);

    $this->get('/news/'.$news->slug)
        ->assertOk()
        ->assertSee('<title data-pacms-head>Schools plant 10,000 trees', false)
        ->assertSee('"@type":"NewsArticle"', false);

    $this->getJson('/api/v1/resolve?path=/news/'.$news->slug)
        ->assertJsonPath('kind', 'content')
        ->assertJsonPath('data.type', 'news')
        ->assertJsonPath('data.title', 'Schools plant 10,000 trees')
        ->assertJsonPath('data.archive_path', '/news');
});

it('lets authors write and submit news but not publish or change published news', function () {
    $author = userWithRole('author', twoFactor: false);

    $this->actingAs($author)->post(route('admin.news.store'), ['title' => 'My story'])->assertRedirect();
    $news = News::firstOrFail();

    $this->actingAs($author)->post(route('admin.news.workflow', $news), ['action' => 'publish'])->assertForbidden();
    $this->actingAs($author)->post(route('admin.news.workflow', $news), ['action' => 'submit'])->assertRedirect();
    expect($news->fresh()->status->value)->toBe('in_review');

    $news->forceFill(['status' => 'published', 'published_at' => now()])->save();
    $this->actingAs($author)->put(route('admin.news.update', $news), ['title' => 'Changed', 'lock_version' => $news->lock_version])->assertForbidden();
    expect($news->fresh()->title)->toBe('My story');
});

it('generates unique slugs and validates input', function () {
    $editor = userWithRole('editor');

    $this->actingAs($editor)->post(route('admin.news.store'), ['title' => 'Same title']);
    $this->actingAs($editor)->post(route('admin.news.store'), ['title' => 'Same title']);
    expect(News::pluck('slug')->all())->toBe(['same-title', 'same-title-2']);

    $this->actingAs($editor)->post(route('admin.news.store'), ['title' => 'Bad', 'slug' => 'Not A Slug'])->assertSessionHasErrors('slug');
});

it('hides unpublished and deleted news everywhere', function () {
    $editor = userWithRole('editor');
    $this->actingAs($editor)->post(route('admin.news.store'), ['title' => 'Gone soon']);
    $news = News::firstOrFail();
    publishNews($news, $editor);
    $this->get('/news/gone-soon')->assertOk();

    $this->actingAs($editor)->post(route('admin.news.workflow', $news), ['action' => 'unpublish']);
    $this->get('/news/gone-soon')->assertNotFound();

    publishNews($news->fresh(), $editor);
    $this->actingAs($editor)->delete(route('admin.news.destroy', $news))->assertRedirect(route('admin.news.index'));
    $this->get('/news/gone-soon')->assertNotFound();
});

it('stores a cleaned article text and shows it on the article page', function () {
    $editor = userWithRole('editor');

    $this->actingAs($editor)->post(route('admin.news.store'), [
        'title' => 'Mangrove day',
        'excerpt' => '<p>We planted <strong>500</strong> trees.<script>x()</script></p>',
        'body' => '<h2>The day</h2><p onclick="steal()">Volunteers came early.</p><ul><li>Tea</li></ul><script>alert(1)</script><a href="javascript:alert(1)">bad</a>',
    ])->assertRedirect();

    $news = News::query()->where('title', 'Mangrove day')->firstOrFail();
    expect($news->body)->toContain('<h2>The day</h2>')->toContain('<li>Tea</li>')
        ->not->toContain('script')->not->toContain('onclick')->not->toContain('javascript:')
        ->and($news->excerpt)->toContain('<strong>500</strong>')->not->toContain('script');

    publishNews($news, $editor);
    $this->getJson('/api/v1/resolve?path=/news/mangrove-day')
        ->assertOk()
        ->assertJsonPath('data.body', $news->fresh()->body)
        ->assertJsonPath('data.excerpt', 'We planted 500 trees.');

    $this->actingAs($editor)->get(route('admin.news.edit', $news))->assertOk()->assertSee('data-rich-editor', false)->assertSee('Article text');
});

it('lists news with an archive page, category filter and pagination', function () {
    $editor = userWithRole('editor');
    $category = Term::query()->create(['taxonomy' => 'news_category', 'name' => 'Climate', 'slug' => 'climate']);

    foreach (range(1, 13) as $n) {
        $this->actingAs($editor)->post(route('admin.news.store'), ['title' => "Story {$n}", 'terms' => $n === 1 ? [$category->id] : []]);
        publishNews(News::query()->latest('id')->firstOrFail(), $editor);
    }

    $this->getJson('/api/v1/resolve?path=/news')
        ->assertJsonPath('kind', 'archive')
        ->assertJsonPath('data.title', 'News')
        ->assertJsonCount(12, 'data.items')
        ->assertJsonPath('data.pagination.pages', 2)
        ->assertJsonPath('data.categories.0.slug', 'climate');

    $this->getJson('/api/v1/resolve?path=/news&page=2')->assertJsonCount(1, 'data.items');
    $this->getJson('/api/v1/resolve?path=/news&category=climate')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.title', 'Story 1')
        ->assertJsonPath('data.category', 'climate');

    $this->get('/news')->assertOk()->assertSee('<title data-pacms-head>News', false);
});

it('keeps a revision for every save and restores one', function () {
    $editor = userWithRole('editor');
    $this->actingAs($editor)->post(route('admin.news.store'), ['title' => 'First title']);
    $news = News::firstOrFail();

    $this->actingAs($editor)->put(route('admin.news.update', $news), ['title' => 'Second title', 'lock_version' => $news->lock_version])->assertRedirect();
    expect($news->fresh()->title)->toBe('Second title')->and($news->revisions()->count())->toBe(2);

    // A stale form is refused instead of silently overwriting the newer save.
    $this->actingAs($editor)->put(route('admin.news.update', $news), ['title' => 'Stale', 'lock_version' => $news->lock_version])
        ->assertSessionHasErrors('lock_version');

    $this->actingAs($editor)->get(route('admin.news.revisions', ['item' => $news, 'from' => 1, 'to' => 2]))
        ->assertOk()->assertSee('Second title');

    $this->actingAs($editor)->post(route('admin.news.revisions.restore', [$news, 1]))->assertRedirect(route('admin.news.edit', $news));
    expect($news->fresh()->title)->toBe('First title')->and($news->revisions()->count())->toBe(3);
});

it('previews unpublished news through a signed link for signed-in editors only', function () {
    $editor = userWithRole('editor');
    $this->actingAs($editor)->post(route('admin.news.store'), ['title' => 'Secret draft']);
    $news = News::firstOrFail();

    $link = $this->actingAs($editor)->get(route('admin.news.preview', $news))->assertRedirect()->headers->get('Location');
    expect($link)->toContain('/preview/news/'.$news->id)->toContain('signature=');

    $this->actingAs($editor)->get($link)
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('Secret draft');

    $this->actingAs($editor)->get('/preview/news/'.$news->id)->assertForbidden();
    auth()->logout();
    $this->get($link)->assertRedirect();
});
