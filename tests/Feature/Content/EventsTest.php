<?php

use App\Cms\Blocks\BlockPayloadResolver;
use App\Cms\Blocks\BlockTreeValidator;
use App\Cms\Content\ContentTypeRegistry;
use App\Enums\WorkflowAction;
use App\Models\Event;
use App\Models\GlobalBlock;
use App\Services\Blocks\GlobalBlockService;
use App\Services\Content\ContentService;
use App\Services\Settings\SettingsService;

/**
 * A published event through the service (the admin form is covered separately).
 *
 * @param  array<string, mixed>  $data
 */
function publishedEvent(array $data): Event
{
    $type = app(ContentTypeRegistry::class)->get('events');
    $service = app(ContentService::class);
    $editor = userWithRole('editor');
    $event = $service->create($type, $editor, $data + ['timezone' => 'Asia/Dhaka']);
    $service->transition($type, $event, WorkflowAction::Publish, $editor);

    return $event->fresh();
}

it('creates an event in its own time zone and shows it on a public page', function () {
    $editor = userWithRole('editor');

    $this->actingAs($editor)->post(route('admin.events.store'), [
        'title' => 'Tree fair',
        'start_at' => '2030-03-12T10:00',
        'end_at' => '2030-03-12T16:00',
        'timezone' => 'Asia/Dhaka',
        'venue' => 'Bangla Academy',
        'registration_url' => 'https://example.org/register',
    ])->assertRedirect();

    $event = Event::query()->firstOrFail();
    // 10:00 in Dhaka (UTC+6) is stored as 04:00 UTC and shown back as 10:00 in the form.
    expect($event->start_at->utc()->format('Y-m-d H:i'))->toBe('2030-03-12 04:00')
        ->and($event->type()->formValue($event, 'start_at'))->toBe('2030-03-12T10:00');

    $this->actingAs($editor)->get(route('admin.events.edit', $event))->assertOk()->assertSee('value="2030-03-12T10:00"', false)->assertSee('Bangla Academy');
    $this->actingAs($editor)->post(route('admin.events.workflow', $event), ['action' => 'publish'])->assertRedirect();

    $this->getJson('/api/v1/resolve?path=/events/tree-fair')
        ->assertJsonPath('kind', 'content')
        ->assertJsonPath('data.type', 'events')
        ->assertJsonPath('data.event.when', '12 March 2030, 10:00–16:00')
        ->assertJsonPath('data.event.venue', 'Bangla Academy')
        ->assertJsonPath('data.event.upcoming', true);

    $this->get('/events/tree-fair')
        ->assertOk()
        ->assertSee('"@type":"Event"', false)
        ->assertSee('"startDate":"2030-03-12T10:00:00+06:00"', false);
});

it('validates event dates and time zones', function () {
    $editor = userWithRole('editor');

    $this->actingAs($editor)->post(route('admin.events.store'), ['title' => 'No date', 'timezone' => 'Asia/Dhaka'])->assertSessionHasErrors('start_at');
    $this->actingAs($editor)->post(route('admin.events.store'), [
        'title' => 'Backwards', 'start_at' => '2030-03-12T10:00', 'end_at' => '2030-03-11T10:00', 'timezone' => 'Asia/Dhaka',
    ])->assertSessionHasErrors('end_at');
    $this->actingAs($editor)->post(route('admin.events.store'), [
        'title' => 'Nowhere', 'start_at' => '2030-03-12T10:00', 'timezone' => 'Mars/Olympus',
    ])->assertSessionHasErrors('timezone');

    expect(Event::query()->count())->toBe(0);
});

it('lists upcoming and past events in the archive and in the Events block', function () {
    publishedEvent(['title' => 'Later', 'start_at' => now()->addDays(20)->format('Y-m-d\TH:i')]);
    publishedEvent(['title' => 'Soon', 'start_at' => now()->addDays(2)->format('Y-m-d\TH:i')]);
    publishedEvent(['title' => 'Last year', 'start_at' => now()->subYear()->format('Y-m-d\TH:i')]);

    $this->getJson('/api/v1/resolve?path=/events')
        ->assertJsonPath('kind', 'archive')
        ->assertJsonPath('data.view', 'upcoming')
        ->assertJsonPath('data.items.0.title', 'Soon')
        ->assertJsonPath('data.items.1.title', 'Later')
        ->assertJsonCount(2, 'data.items');

    $this->getJson('/api/v1/resolve?path=/events&view=past')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.title', 'Last year')
        ->assertJsonPath('data.items.0.meta.status', 'Past event');

    // Crafted query values fall back to the defaults instead of failing.
    $this->getJson('/api/v1/resolve?path=/events&view[]=x&page[]=2')->assertOk()->assertJsonPath('data.view', 'upcoming');

    $clean = app(BlockTreeValidator::class)->validate([['type' => 'events']], userWithRole('super-admin'));
    $block = app(BlockPayloadResolver::class)->resolve($clean)[0];
    expect(array_column($block['items'], 'title'))->toBe(['Soon', 'Later']);
});

it('shows the module sidebar unless the item chooses another or none', function () {
    $admin = userWithRole('super-admin');
    $globals = app(GlobalBlockService::class);
    $make = function (string $name, string $text) use ($globals, $admin): GlobalBlock {
        $global = $globals->create($admin, ['name' => $name, 'kind' => 'sidebar', 'blocks' => [['type' => 'heading', 'content' => ['text' => $text, 'level' => '2']]]]);
        $globals->publish($admin, $global);

        return $global->fresh();
    };
    $default = $make('Events sidebar', 'Upcoming highlights');
    $special = $make('Fair sidebar', 'Fair partners');

    $this->actingAs($admin)->get(route('admin.settings.general'))->assertOk()->assertSee('Events sidebar');
    $this->actingAs($admin)->put(route('admin.settings.general.update'), [
        'name' => 'Example', 'timezone' => 'Asia/Dhaka',
        'sidebars' => ['events' => ['global_block_id' => $default->id, 'position' => 'left']],
    ])->assertRedirect();
    $saved = app(SettingsService::class)->get('content', 'sidebars');
    expect($saved['events'])->toEqual(['global_block_id' => $default->id, 'position' => 'left'])
        ->and($saved['news'])->toEqual(['global_block_id' => null, 'position' => 'right']);

    $event = publishedEvent(['title' => 'Fair', 'start_at' => now()->addDays(5)->format('Y-m-d\TH:i')]);
    $this->getJson('/api/v1/resolve?path=/events/fair')
        ->assertJsonPath('data.sidebar.position', 'left')
        ->assertJsonPath('data.sidebar.blocks.0.children.0.content.text', 'Upcoming highlights');

    $this->actingAs($admin)->put(route('admin.events.update', $event), [
        'title' => 'Fair', 'start_at' => now()->addDays(5)->format('Y-m-d\TH:i'), 'timezone' => 'Asia/Dhaka',
        'sidebar_mode' => 'custom', 'sidebar_global_block_id' => $special->id, 'lock_version' => $event->lock_version,
    ])->assertRedirect();
    $this->getJson('/api/v1/resolve?path=/events/fair')->assertJsonPath('data.sidebar.blocks.0.children.0.content.text', 'Fair partners');

    $event->refresh();
    $this->actingAs($admin)->put(route('admin.events.update', $event), [
        'title' => 'Fair', 'start_at' => now()->addDays(5)->format('Y-m-d\TH:i'), 'timezone' => 'Asia/Dhaka',
        'sidebar_mode' => 'none', 'sidebar_global_block_id' => $special->id, 'lock_version' => $event->lock_version,
    ])->assertRedirect();
    expect($event->fresh()->sidebar_global_block_id)->toBeNull();
    $this->getJson('/api/v1/resolve?path=/events/fair')->assertJsonPath('data.sidebar', null);
});

it('offers any published global block as a sidebar, Sidebar kind first', function () {
    $admin = userWithRole('super-admin');
    $globals = app(GlobalBlockService::class);
    $draft = $globals->create($admin, ['name' => 'Draft box', 'blocks' => []]);
    $generic = $globals->publish($admin, $globals->create($admin, ['name' => 'A banner', 'blocks' => []]));
    $sidebar = $globals->publish($admin, $globals->create($admin, ['name' => 'Z sidebar', 'kind' => 'sidebar', 'blocks' => []]));
    $fields = ['title' => 'Fair', 'start_at' => '2030-01-01T10:00', 'timezone' => 'Asia/Dhaka', 'sidebar_mode' => 'custom'];

    // Unpublished blocks would show nothing on the site, so they are refused.
    $this->actingAs($admin)->post(route('admin.events.store'), $fields + ['sidebar_global_block_id' => $draft->id])
        ->assertSessionHasErrors('sidebar_global_block_id');
    $this->actingAs($admin)->post(route('admin.events.store'), $fields + ['sidebar_global_block_id' => $generic->id])
        ->assertSessionHasNoErrors();
    expect(Event::query()->firstOrFail()->sidebar_global_block_id)->toBe($generic->id);

    $this->actingAs($admin)->get(route('admin.events.create'))
        ->assertOk()
        ->assertSeeInOrder(['<optgroup label="Sidebars">', 'Z sidebar', '<optgroup label="Other global blocks">', 'A banner'], false)
        ->assertDontSee('Draft box');
});

it('publishes scheduled events when their time comes', function () {
    $type = app(ContentTypeRegistry::class)->get('events');
    $editor = userWithRole('editor');
    $event = app(ContentService::class)->create($type, $editor, ['title' => 'Planned', 'start_at' => '2030-01-01T10:00', 'timezone' => 'Asia/Dhaka']);

    $this->actingAs($editor)->post(route('admin.events.workflow', $event), ['action' => 'schedule', 'publish_at' => now()->addHour()->setTimezone((string) app(SettingsService::class)->get('site', 'timezone', 'UTC'))->format('Y-m-d\TH:i')])->assertRedirect();
    // The form sends site-local time (Settings → time zone); it is stored in UTC.
    expect($event->fresh()->publish_at)->not->toBeNull();

    $this->travel(2)->hours();
    $this->artisan('pacms:publish-scheduled')->assertSuccessful();
    expect($event->fresh()->isPublished())->toBeTrue();
});

it('shows Events in the admin navigation for users who may view them', function () {
    $this->actingAs(userWithRole('editor'))->get(route('admin.dashboard'))
        ->assertSee(route('admin.events.index'), false)
        ->assertSee('Event categories');

    $this->actingAs(userWithRole('moderator', twoFactor: false))->get(route('admin.dashboard'))
        ->assertDontSee(route('admin.events.index'), false);
});

it('keeps the sidebar position without a default sidebar, for items with their own', function () {
    $admin = userWithRole('super-admin');
    $globals = app(GlobalBlockService::class);
    $own = $globals->publish($admin, $globals->create($admin, ['name' => 'Left Side Bar', 'blocks' => [['type' => 'heading', 'content' => ['text' => 'Partners', 'level' => '2']]]]));

    $this->actingAs($admin)->put(route('admin.settings.general.update'), [
        'name' => 'Example', 'timezone' => 'Asia/Dhaka',
        'sidebars' => ['events' => ['global_block_id' => '', 'position' => 'left']],
    ])->assertRedirect();

    $event = publishedEvent(['title' => 'Fair', 'start_at' => now()->addDays(5)->format('Y-m-d\TH:i')]);
    $this->getJson('/api/v1/resolve?path=/events/fair')->assertJsonPath('data.sidebar', null);

    $this->actingAs($admin)->put(route('admin.events.update', $event), [
        'title' => 'Fair', 'start_at' => now()->addDays(5)->format('Y-m-d\TH:i'), 'timezone' => 'Asia/Dhaka',
        'sidebar_mode' => 'custom', 'sidebar_global_block_id' => $own->id, 'lock_version' => $event->lock_version,
    ])->assertRedirect();
    $this->getJson('/api/v1/resolve?path=/events/fair')
        ->assertJsonPath('data.sidebar.position', 'left')
        ->assertJsonPath('data.sidebar.blocks.0.children.0.content.text', 'Partners');
});
