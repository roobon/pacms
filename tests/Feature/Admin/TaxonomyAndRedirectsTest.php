<?php

use App\Models\Media;
use App\Models\Redirect;
use App\Models\Term;

it('manages categories with automatic slugs and hierarchy', function () {
    $editor = userWithRole('editor');

    $this->actingAs($editor)->post(route('admin.terms.store', 'media_category'), ['name' => 'Eco Schools'])->assertSessionHasNoErrors();
    $parent = Term::where('slug', 'eco-schools')->firstOrFail();

    $this->actingAs($editor)->post(route('admin.terms.store', 'media_category'), ['name' => 'Events', 'parent_id' => $parent->id])->assertSessionHasNoErrors();
    $this->actingAs($editor)->post(route('admin.terms.store', 'media_category'), ['name' => 'Eco Schools'])->assertSessionHasErrors('slug');

    $this->actingAs($editor)->get(route('admin.terms.index', 'media_category'))->assertOk()->assertSee('Eco Schools')->assertSee('Events');
    expect(Term::where('slug', 'events')->value('parent_id'))->toBe($parent->id);
});

it('does not allow parents on flat taxonomies or unknown taxonomies', function () {
    $editor = userWithRole('editor');
    $tag = Term::create(['taxonomy' => 'tag', 'name' => 'Climate', 'slug' => 'climate']);

    $this->actingAs($editor)->post(route('admin.terms.store', 'tag'), ['name' => 'Water', 'parent_id' => $tag->id])->assertSessionHasErrors('parent_id');
    $this->actingAs($editor)->get('/admin/taxonomies/secret_taxonomy')->assertNotFound();
});

it('blocks deleting terms that are in use', function () {
    $editor = userWithRole('editor');
    $term = Term::create(['taxonomy' => 'media_category', 'name' => 'Photos', 'slug' => 'photos']);

    $this->actingAs($editor)->post(route('admin.media.store'), ['files' => [fakeJpeg()]]);
    Media::firstOrFail()->syncTerms('media_category', [$term->id]);

    $this->actingAs($editor)->delete(route('admin.terms.destroy', ['media_category', $term]))->assertSessionHasErrors('term');

    Media::firstOrFail()->syncTerms('media_category', []);
    $this->actingAs($editor)->delete(route('admin.terms.destroy', ['media_category', $term]))->assertSessionHasNoErrors();
    expect(Term::withTrashed()->count())->toBe(0);
});

it('requires taxonomies.manage for term screens', function () {
    $this->actingAs(userWithRole('author', twoFactor: false))->get(route('admin.terms.index', 'tag'))->assertForbidden();
});

it('manages manual redirects that work on the public site', function () {
    $editor = userWithRole('editor');

    $this->actingAs($editor)->post(route('admin.redirects.store'), [
        'source_path' => 'Old-Page/', 'target_path' => '/new-page', 'status_code' => 302,
    ])->assertSessionHasNoErrors();

    expect(Redirect::firstOrFail()->source_path)->toBe('/old-page');
    $this->get('/old-page')->assertRedirect('/new-page')->assertStatus(302);

    $this->actingAs($editor)->post(route('admin.redirects.store'), [
        'source_path' => '/evil', 'target_path' => 'javascript:alert(1)', 'status_code' => 301,
    ])->assertSessionHasErrors('target_path');

    $this->actingAs($editor)->delete(route('admin.redirects.destroy', Redirect::firstOrFail()))->assertSessionHasNoErrors();
    $this->get('/old-page')->assertNotFound();
});

it('lets a live page win over a stale redirect with the same path', function () {
    Redirect::create(['source_path' => '/contact', 'target_path' => '/elsewhere', 'status_code' => 301]);
    livePage(['title' => 'Contact']);

    $this->get('/contact')->assertOk();
});
