<?php

use App\Models\News;

it('lets an editor create and publish news, which then has a public page', function () {
    $editor = userWithRole('editor');

    $this->actingAs($editor)->post(route('admin.news.store'), ['title' => 'Schools plant 10,000 trees', 'excerpt' => 'A record year.'])
        ->assertRedirect();
    $news = News::firstOrFail();
    expect($news->slug)->toBe('schools-plant-10000-trees');

    $this->get('/news/'.$news->slug)->assertNotFound();

    $this->actingAs($editor)->post(route('admin.news.publish', $news))->assertRedirect();

    $this->get('/news/'.$news->slug)
        ->assertOk()
        ->assertSee('<title data-pacms-head>Schools plant 10,000 trees', false)
        ->assertSee('"@type":"NewsArticle"', false);

    $this->getJson('/api/v1/resolve?path=/news/'.$news->slug)->assertJsonPath('kind', 'news')->assertJsonPath('data.title', 'Schools plant 10,000 trees');
});

it('lets authors write news but not publish or change published news', function () {
    $author = userWithRole('author', twoFactor: false);

    $this->actingAs($author)->post(route('admin.news.store'), ['title' => 'My story'])->assertRedirect();
    $news = News::firstOrFail();

    $this->actingAs($author)->post(route('admin.news.publish', $news))->assertForbidden();

    $news->forceFill(['status' => 'published', 'published_at' => now()])->save();
    $this->actingAs($author)->put(route('admin.news.update', $news), ['title' => 'Changed'])->assertForbidden();
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
    $this->actingAs($editor)->post(route('admin.news.publish', $news));
    $this->get('/news/gone-soon')->assertOk();

    $this->actingAs($editor)->delete(route('admin.news.destroy', $news))->assertRedirect();
    $this->get('/news/gone-soon')->assertNotFound();
});
