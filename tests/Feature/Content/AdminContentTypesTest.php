<?php

use App\Cms\Content\ContentTypeRegistry;
use App\Models\CustomContentType;
use App\Models\CustomItem;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake((string) config('pacms.media.disk'));
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function storiesType(array $overrides = []): array
{
    return array_merge([
        'label' => 'Success stories',
        'singular' => 'success story',
        'icon' => 'bi-trophy',
        'route_prefix' => '',
        'workflow' => 'editorial',
        'has_archive' => '1',
        'searchable' => '1',
        'fields' => json_encode([
            ['key' => 'school', 'type' => 'text', 'label' => 'School', 'required' => true],
            ['key' => 'district', 'type' => 'select', 'label' => 'District', 'options' => ['dhaka' => 'Dhaka', 'khulna' => 'Khulna']],
            ['key' => 'year', 'type' => 'number', 'label' => 'Year'],
            ['key' => 'themes', 'type' => 'multi-select', 'label' => 'Themes', 'options' => ['waste' => 'Waste', 'water' => 'Water', 'trees' => 'Trees']],
            ['key' => 'story', 'type' => 'rich-text', 'label' => 'The story'],
            ['key' => 'internal_note', 'type' => 'textarea', 'label' => 'Internal note'],
            ['key' => 'milestones', 'type' => 'repeater', 'label' => 'Milestones', 'fields' => [
                ['key' => 'when', 'type' => 'text', 'label' => 'When'],
                ['key' => 'what', 'type' => 'textarea', 'label' => 'What'],
            ]],
        ]),
        'display' => ['internal_note' => 'hidden'],
    ], $overrides);
}

function makeStoriesType(array $overrides = []): CustomContentType
{
    test()->actingAs(userWithRole('administrator'))->post(route('admin.content-types.store'), storiesType($overrides))->assertSessionHasNoErrors();

    return CustomContentType::query()->latest('id')->firstOrFail();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function storyInput(array $overrides = []): array
{
    return array_merge([
        'title' => 'Green Flag for Dhaka Model School',
        'school' => 'Dhaka Model School',
        'district' => 'dhaka',
        'year' => '2025',
        'themes' => ['waste', 'water'],
        'story' => '<p>It <strong>worked</strong>.</p><script>alert(1)</script>',
        'internal_note' => 'Ask the head teacher for photos',
        'milestones' => [['when' => '2023', 'what' => 'Eco-Committee formed'], ['when' => '2025', 'what' => 'Green Flag']],
    ], $overrides);
}

it('lets only people with "Manage content types" create types', function () {
    $this->actingAs(userWithRole('editor'))->get(route('admin.content-types.index'))->assertForbidden();
    $this->actingAs(userWithRole('administrator'))->get(route('admin.content-types.create'))->assertOk()->assertSee('How items are published');
});

it('creates a type with its permissions, menu entry and admin screens at once', function () {
    $type = makeStoriesType();

    expect($type->key)->toBe('success_stories')
        ->and($type->route_prefix)->toBe('success-stories')
        ->and(app(ContentTypeRegistry::class)->find('success_stories'))->not->toBeNull()
        ->and(Permission::query()->where('name', 'like', 'success_stories.%')->count())->toBe(8);

    $editor = userWithRole('editor');
    $author = userWithRole('author', twoFactor: false);
    expect($editor->can('success_stories.publish'))->toBeTrue()
        ->and($author->can('success_stories.create'))->toBeTrue()
        ->and($author->can('success_stories.publish'))->toBeFalse();

    $this->actingAs($editor)->get(route('admin.dashboard'))->assertSee('Success stories')->assertSee(route('admin.types.index', ['type' => 'success_stories']));
    $this->actingAs($editor)->get(route('admin.types.create', ['type' => 'success_stories']))->assertOk()
        ->assertSee('Success story details')->assertSee('School')->assertSee('Themes')->assertSee('data-rich-editor', false);
    // The type's permissions are listed in Roles & Permissions.
    $this->actingAs(userWithRole('super-admin'))->get(route('admin.roles.edit', Role::findByName('editor')))->assertSee('Success stories (content type)');
});

it('refuses addresses and fields that clash with what exists', function () {
    $admin = userWithRole('administrator');

    $this->actingAs($admin)->post(route('admin.content-types.store'), storiesType(['route_prefix' => 'news']))->assertSessionHasErrors('route_prefix');
    livePage(['title' => 'Stories', 'slug' => 'stories']);
    $this->actingAs($admin)->post(route('admin.content-types.store'), storiesType(['route_prefix' => 'stories']))->assertSessionHasErrors('route_prefix');
    $this->actingAs($admin)->post(route('admin.content-types.store'), storiesType(['fields' => json_encode([['key' => 'title', 'type' => 'text', 'label' => 'Title again']])]))
        ->assertSessionHasErrors('fields.0.key');
    $this->actingAs($admin)->post(route('admin.content-types.store'), storiesType(['fields' => json_encode([['key' => 'colour', 'type' => 'color', 'label' => 'Colour']])]))
        ->assertSessionHasErrors('fields.0.type');
    $this->actingAs($admin)->post(route('admin.content-types.store'), storiesType(['fields' => json_encode([['key' => 'rows', 'type' => 'repeater', 'label' => 'Rows', 'fields' => [['key' => 'pic', 'type' => 'image', 'label' => 'Pic']]]])]))
        ->assertSessionHasErrors('fields.0.fields.0.type');
    expect(CustomContentType::query()->count())->toBe(0);

    // Once a type exists, a top-level page cannot take its address.
    makeStoriesType();
    $this->actingAs(userWithRole('editor'))->post(route('admin.pages.store'), pagePayload(['title' => 'Success stories', 'slug' => 'success-stories']))->assertSessionHasErrors('slug');
});

it('stores items with their own fields and shows them on the website', function () {
    makeStoriesType();
    $editor = userWithRole('editor');

    $this->actingAs($editor)->post(route('admin.types.store', ['type' => 'success_stories']), storyInput(['school' => '']))->assertSessionHasErrors('school');
    $this->actingAs($editor)->post(route('admin.types.store', ['type' => 'success_stories']), storyInput(['themes' => ['lava']]))->assertSessionHasErrors('themes.0');
    $this->actingAs($editor)->post(route('admin.types.store', ['type' => 'success_stories']), storyInput())->assertSessionHasNoErrors();

    $item = CustomItem::query()->firstOrFail();
    expect($item->school)->toBe('Dhaka Model School')
        ->and($item->story)->toBe('<p>It <strong>worked</strong>.</p>')
        ->and($item->themes)->toBe(['waste', 'water'])
        ->and($item->fields)->toHaveKey('district');

    $this->actingAs($editor)->post(route('admin.types.workflow', ['type' => 'success_stories', 'item' => $item->id]), ['action' => 'publish'])->assertRedirect();

    $data = $this->getJson('/api/v1/resolve?path=/success-stories/green-flag-for-dhaka-model-school')->assertJsonPath('kind', 'content')->json('data');
    expect($data['facts'])->toBe([
        ['label' => 'School', 'value' => 'Dhaka Model School'],
        ['label' => 'District', 'value' => 'Dhaka'],
        ['label' => 'Year', 'value' => '2025'],
        ['label' => 'Themes', 'value' => 'Waste, Water'],
    ])
        ->and(array_column($data['sections'], 'key'))->toBe(['story', 'milestones'])
        ->and($data['sections'][1]['rows'][0])->toBe(['when' => '2023', 'what' => 'Eco-Committee formed']);
    // Hidden fields never reach the website.
    $this->getJson('/api/v1/resolve?path=/success-stories/green-flag-for-dhaka-model-school')->assertDontSee('Ask the head teacher');

    $this->getJson('/api/v1/resolve?path=/success-stories')->assertJsonPath('kind', 'archive')->assertJsonPath('data.items.0.meta.role', 'Dhaka Model School');
    $this->get('/sitemaps/success-stories.xml')->assertOk()->assertSee('/success-stories/green-flag-for-dhaka-model-school');
});

it('follows the editorial workflow and permissions of the type', function () {
    makeStoriesType();
    $author = userWithRole('author', twoFactor: false);

    $this->actingAs($author)->post(route('admin.types.store', ['type' => 'success_stories']), storyInput())->assertSessionHasNoErrors();
    $item = CustomItem::query()->firstOrFail();
    $this->actingAs($author)->post(route('admin.types.workflow', ['type' => 'success_stories', 'item' => $item->id]), ['action' => 'publish'])->assertForbidden();
    $this->actingAs($author)->post(route('admin.types.workflow', ['type' => 'success_stories', 'item' => $item->id]), ['action' => 'submit'])->assertRedirect();
    $this->getJson('/api/v1/resolve?path=/success-stories/green-flag-for-dhaka-model-school')->assertJsonPath('kind', 'not_found');

    // Users without the type's permissions see nothing.
    $this->actingAs(userWithRole('moderator'))->get(route('admin.types.index', ['type' => 'success_stories']))->assertForbidden();
    // Built-in modules are not reachable through the admin-made routes, and the other way round.
    $this->actingAs(userWithRole('editor'))->get('/admin/types/news')->assertNotFound();
});

it('keeps revisions of the type\'s own fields', function () {
    makeStoriesType();
    $editor = userWithRole('editor');
    $this->actingAs($editor)->post(route('admin.types.store', ['type' => 'success_stories']), storyInput())->assertSessionHasNoErrors();
    $item = CustomItem::query()->firstOrFail();

    $this->actingAs($editor)->put(route('admin.types.update', ['type' => 'success_stories', 'item' => $item->id]), storyInput(['school' => 'Renamed School', 'lock_version' => $item->lock_version]))->assertSessionHasNoErrors();
    expect($item->fresh()->school)->toBe('Renamed School');

    $this->actingAs($editor)->post(route('admin.types.revisions.restore', ['type' => 'success_stories', 'item' => $item->id, 'revision' => 1]))->assertRedirect();
    expect($item->fresh()->school)->toBe('Dhaka Model School');
});

it('supports simple active / inactive types with a display order and no listing page', function () {
    makeStoriesType(['label' => 'Board members', 'singular' => 'board member', 'workflow' => 'managed', 'has_archive' => '0',
        'fields' => json_encode([['key' => 'role', 'type' => 'text', 'label' => 'Role']])]);
    expect(Permission::query()->where('name', 'board_members.manage')->exists())->toBeTrue();

    $editor = userWithRole('editor');
    foreach ([['Second', 2], ['First', 1]] as [$name, $position]) {
        $this->actingAs($editor)->post(route('admin.types.store', ['type' => 'board_members']), ['title' => $name, 'role' => 'Member', 'position' => $position])->assertSessionHasNoErrors();
        $this->actingAs($editor)->post(route('admin.types.workflow', ['type' => 'board_members', 'item' => CustomItem::query()->latest('id')->value('id')]), ['action' => 'publish']);
    }
    $this->actingAs(userWithRole('author', twoFactor: false))->get(route('admin.types.index', ['type' => 'board_members']))->assertForbidden();

    $this->getJson('/api/v1/resolve?path=/board-members')->assertJsonPath('kind', 'not_found');
    $this->getJson('/api/v1/resolve?path=/board-members/first')->assertJsonPath('kind', 'content');
    $type = app(ContentTypeRegistry::class)->get('board_members');
    expect($type->query()->published()->tap(fn ($q) => $type->applyOrder($q, 'position'))->pluck('title')->all())->toBe(['First', 'Second']);
});

it('disables a type without losing items, and deletes only empty types', function () {
    $type = makeStoriesType();
    $admin = userWithRole('administrator');
    $editor = userWithRole('editor');
    $this->actingAs($editor)->post(route('admin.types.store', ['type' => 'success_stories']), storyInput())->assertSessionHasNoErrors();
    $item = CustomItem::query()->firstOrFail();
    $this->actingAs($editor)->post(route('admin.types.workflow', ['type' => 'success_stories', 'item' => $item->id]), ['action' => 'publish']);

    // The edit form has no workflow choice (it cannot be changed after creation).
    $update = fn (array $changes) => $this->actingAs($admin)->put(route('admin.content-types.update', $type), array_merge(Arr::except(storiesType(), 'workflow'), ['is_active' => '1'], $changes));

    $update(['is_active' => '0'])->assertSessionHasNoErrors();
    $this->getJson('/api/v1/resolve?path=/success-stories/green-flag-for-dhaka-model-school')->assertJsonPath('kind', 'not_found');
    $this->actingAs($editor)->get(route('admin.types.index', ['type' => 'success_stories']))->assertNotFound();
    expect(CustomItem::query()->count())->toBe(1);

    $update(['is_active' => '1'])->assertSessionHasNoErrors();
    $this->getJson('/api/v1/resolve?path=/success-stories/green-flag-for-dhaka-model-school')->assertJsonPath('kind', 'content');

    // The workflow cannot be changed afterwards.
    $update(['workflow' => 'managed'])->assertSessionHasErrors('workflow');

    $this->actingAs($admin)->delete(route('admin.content-types.destroy', $type))->assertSessionHasErrors('type');
    $this->actingAs($editor)->delete(route('admin.types.destroy', ['type' => 'success_stories', 'item' => $item->id]))->assertRedirect();
    $this->actingAs($admin)->delete(route('admin.content-types.destroy', $type))->assertRedirect(route('admin.content-types.index'));
    expect(CustomContentType::query()->count())->toBe(0)
        ->and(Permission::query()->where('name', 'like', 'success_stories.%')->count())->toBe(0)
        ->and(User::query()->count())->toBeGreaterThan(0);
});
