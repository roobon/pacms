<?php

use App\Enums\WorkflowAction;
use App\Models\BlockType;
use App\Models\Page;
use App\Services\Blocks\CustomBlockTypeService;
use App\Services\Publishing\PublishingService;
use Illuminate\Validation\ValidationException;

function staffFields(): array
{
    return [
        ['key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
        ['key' => 'role', 'type' => 'select', 'label' => 'Role', 'options' => ['staff' => 'Staff', 'volunteer' => 'Volunteer']],
        ['key' => 'bio', 'type' => 'textarea', 'label' => 'Biography'],
        ['key' => 'links', 'type' => 'repeater', 'label' => 'Links', 'fields' => [
            ['key' => 'label', 'type' => 'text', 'label' => 'Label', 'required' => true],
            ['key' => 'url', 'type' => 'url', 'label' => 'Address'],
        ]],
    ];
}

function staffStructure(): array
{
    return [[
        'uuid' => '01k00000000000000000000sec',
        'type' => 'section',
        'children' => [
            ['uuid' => '01k00000000000000000000hdg', 'type' => 'heading', 'content' => ['text' => ['$bind' => 'name'], 'level' => '3']],
            ['uuid' => '01k00000000000000000000bio', 'type' => 'rich-text', 'content' => ['html' => ['$bind' => 'bio']]],
            ['uuid' => '01k00000000000000000000whn', 'type' => 'when', 'content' => ['field' => 'role', 'operator' => 'equals', 'value' => 'volunteer'], 'children' => [
                ['uuid' => '01k00000000000000000000vol', 'type' => 'heading', 'content' => ['text' => ['$bind' => 'role'], 'level' => '4']],
            ]],
            ['uuid' => '01k00000000000000000000rpt', 'type' => 'repeat', 'content' => ['field' => 'links'], 'children' => [
                ['uuid' => '01k00000000000000000000btn', 'type' => 'button', 'content' => ['label' => ['$bind' => 'item.label'], 'link' => ['$bind' => 'item.url']]],
            ]],
        ],
    ]];
}

function publishedStaffType(): BlockType
{
    $service = app(CustomBlockTypeService::class);
    $admin = userWithRole('administrator');
    $type = $service->create($admin, ['name' => 'Staff profile', 'icon' => 'bi-person-badge']);
    $service->update($admin, $type, ['name' => 'Staff profile', 'fields' => staffFields(), 'blocks' => staffStructure()], $type->fresh()->lock_version);

    return $service->publish($admin, $type->fresh());
}

function pageWithStaff(array $content): Page
{
    $page = livePage(['title' => 'Our people']);
    $editor = userWithRole('editor');

    test()->actingAs($editor)->put(route('admin.pages.update', $page), pagePayload([
        'title' => 'Our people', 'slug' => 'our-people', 'lock_version' => $page->fresh()->lock_version,
        'blocks' => json_encode([['uuid' => '01k00000000000000000000ins', 'type' => 'custom/staff-profile', 'content' => $content]]),
    ]))->assertSessionHasNoErrors();
    app(PublishingService::class)->transition($page->fresh(), WorkflowAction::Publish, $editor);

    return $page->fresh();
}

it('publishes a custom block type with a version and offers it in the builder', function () {
    $type = publishedStaffType();

    expect($type->slug)->toBe('custom/staff-profile')
        ->and($type->version)->toBe(1)
        ->and($type->status)->toBe('published');

    $this->actingAs(userWithRole('editor'))->getJson(route('admin.api.blocks.definitions'))
        ->assertOk()
        ->assertJsonFragment(['slug' => 'custom/staff-profile', 'custom' => true, 'insertable' => true]);
});

it('expands instances on the server: bindings, conditions and repeated rows', function () {
    publishedStaffType();
    pageWithStaff([
        'name' => 'Amina Rahman',
        'role' => 'volunteer',
        'bio' => "Leads <script>alert(1)</script> workshops.\nSecond line",
        'links' => [['label' => 'Email', 'url' => 'mailto:amina@example.org'], ['label' => 'Website', 'url' => 'https://example.org']],
    ]);

    $block = $this->getJson('/api/v1/pages/our-people')->assertOk()->json('data.blocks.0');
    $section = $block['children'][0];
    $types = array_column($section['children'], 'type');

    expect($block['type'])->toBe('custom/staff-profile')
        ->and($section['locked'])->toBeTrue()
        ->and($section['children'][0]['content']['text'])->toBe('Amina Rahman')
        // Plain text bound into rich text is escaped, never interpreted as HTML.
        ->and($section['children'][1]['content']['html'])->toContain('&lt;script&gt;')->not->toContain('<script>')
        // "when role equals volunteer" shows the option label.
        ->and($section['children'][2]['content']['text'])->toBe('Volunteer')
        // One button per repeater row, with unique uuids.
        ->and($types)->toBe(['heading', 'rich-text', 'heading', 'button', 'button'])
        ->and($section['children'][3]['content']['label'])->toBe('Email')
        ->and($section['children'][4]['content']['link']['href'])->toBe('https://example.org')
        ->and($section['children'][3]['uuid'])->not->toBe($section['children'][4]['uuid']);

    // No binding or structural block reaches the browser.
    expect(json_encode($block))->not->toContain('$bind')->not->toContain('"repeat"')->not->toContain('"when"');
});

it('hides conditional parts and validates instance values against the type’s fields', function () {
    publishedStaffType();
    pageWithStaff(['name' => 'Karim', 'role' => 'staff']);

    $section = $this->getJson('/api/v1/pages/our-people')->json('data.blocks.0.children.0');
    expect(array_column($section['children'], 'type'))->toBe(['heading']);

    $this->actingAs(userWithRole('editor'))->postJson(route('admin.api.blocks.resolve'), ['blocks' => [
        ['uuid' => '01k00000000000000000noname', 'type' => 'custom/staff-profile', 'content' => ['role' => 'boss']],
    ]])->assertJsonValidationErrors(['blocks.01k00000000000000000noname.content.name', 'blocks.01k00000000000000000noname.content.role']);
});

it('rejects invalid field definitions and bindings', function () {
    $service = app(CustomBlockTypeService::class);
    $admin = userWithRole('administrator');
    $type = $service->create($admin, ['name' => 'Broken']);

    expect(fn () => $service->update($admin, $type, ['name' => 'Broken', 'fields' => [
        ['key' => 'Bad Key', 'type' => 'text', 'label' => 'X'],
        ['key' => 'html', 'type' => 'script', 'label' => 'Y'],
    ], 'blocks' => []], $type->lock_version))->toThrow(ValidationException::class);

    // Binding a number field into an image, and an unknown field, are refused.
    try {
        $service->update($admin, $type->fresh(), ['name' => 'Broken', 'fields' => [['key' => 'count', 'type' => 'number', 'label' => 'Count']], 'blocks' => [
            ['uuid' => '01k0000000000000000000imgx', 'type' => 'image', 'content' => ['image' => ['$bind' => 'count']]],
            ['uuid' => '01k0000000000000000000hdgx', 'type' => 'heading', 'content' => ['text' => ['$bind' => 'missing'], 'level' => '2']],
        ]], $type->fresh()->lock_version);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKeys(['blocks.01k0000000000000000000imgx.content.image', 'blocks.01k0000000000000000000hdgx.content.text']);
    }

    // Bindings are not accepted outside custom block structures.
    $this->actingAs(userWithRole('editor'))->postJson(route('admin.api.blocks.resolve'), ['blocks' => [
        ['uuid' => '01k0000000000000000000bndx', 'type' => 'heading', 'content' => ['text' => ['$bind' => 'name'], 'level' => '2']],
    ]])->assertJsonValidationErrors('blocks.01k0000000000000000000bndx.content.text');
});

it('previews an unsaved structure with sample values', function () {
    $this->actingAs(userWithRole('administrator'))->postJson(route('admin.api.blocks.resolve'), [
        'context' => 'structure', 'fields' => staffFields(), 'blocks' => staffStructure(),
    ])
        ->assertOk()
        ->assertJsonPath('blocks.0.uuid', '01k00000000000000000000sec')
        ->assertJsonPath('blocks.0.children.0.content.text', '[Name]');
});

it('keeps disabled types rendering, blocks deleting used types, and restricts management', function () {
    $type = publishedStaffType();
    pageWithStaff(['name' => 'Rafi']);
    $admin = userWithRole('administrator');

    $this->actingAs($admin)->post(route('admin.block-types.toggle', $type))->assertRedirect();
    expect($type->fresh()->status)->toBe('disabled');
    $this->getJson('/api/v1/pages/our-people')->assertJsonPath('data.blocks.0.children.0.children.0.content.text', 'Rafi');

    $this->actingAs($admin)->delete(route('admin.block-types.destroy', $type))->assertSessionHasErrors('block_type');
    expect($type->fresh())->not->toBeNull();

    $this->actingAs(userWithRole('editor'))->get(route('admin.block-types.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.block-types.edit', $type))->assertOk()->assertSee('Fields and layout');
});

it('re-renders existing instances when a new version is published', function () {
    $type = publishedStaffType();
    pageWithStaff(['name' => 'Nadia']);
    $service = app(CustomBlockTypeService::class);
    $admin = userWithRole('administrator');

    $structure = staffStructure();
    $structure[0]['children'][0]['content']['level'] = '2';
    $service->update($admin, $type->fresh(), ['name' => 'Staff profile', 'fields' => staffFields(), 'blocks' => $structure], $type->fresh()->lock_version);
    $this->getJson('/api/v1/pages/our-people')->assertJsonPath('data.blocks.0.children.0.children.0.content.level', '3');

    $service->publish($admin, $type->fresh());
    expect($type->fresh()->version)->toBe(2);
    $this->getJson('/api/v1/pages/our-people')->assertJsonPath('data.blocks.0.children.0.children.0.content.level', '2');
});
