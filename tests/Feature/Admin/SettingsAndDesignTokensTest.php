<?php

use App\Cms\Design\DesignTokenService;
use App\Models\ActivityLog;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Storage;

it('saves general settings and exposes them publicly', function () {
    $this->actingAs(userWithRole('administrator'))
        ->put(route('admin.settings.general.update'), [
            'name' => 'Green Future Foundation',
            'tagline' => 'Education for sustainability',
            'description' => 'We support schools.',
            'contact_email' => 'hello@example.org',
            'contact_phone' => '',
            'address' => '',
            'timezone' => 'Asia/Dhaka',
        ])
        ->assertSessionHasNoErrors();

    expect(app(SettingsService::class)->get('site', 'name'))->toBe('Green Future Foundation');

    $this->getJson('/api/v1/site')->assertJsonPath('data.name', 'Green Future Foundation');
    expect(ActivityLog::where('action', 'settings.updated')->exists())->toBeTrue();
});

it('rejects unknown setting keys at the service level', function () {
    app(SettingsService::class)->set('site', ['injected' => 'x']);
})->throws(InvalidArgumentException::class);

it('publishes design tokens as a hashed stylesheet', function () {
    $this->actingAs(userWithRole('administrator'))
        ->put(route('admin.design.tokens.update'), ['tokens' => ['color.primary' => '#123456', 'font.body' => 'system']])
        ->assertRedirect(route('admin.design.tokens'));

    $path = app(SettingsService::class)->get('design', 'stylesheet');
    expect($path)->toMatch('#^theme/tokens-[0-9a-f]{12}\.css$#');

    $css = Storage::disk('public')->get($path);
    expect($css)->toContain('--pa-color-primary: #123456;')
        ->toContain('--pa-color-primary-rgb: 18, 52, 86;')
        ->toContain('--pa-font-body: system-ui');
});

it('rejects invalid or fixed token values (no CSS injection)', function (array $tokens) {
    $this->actingAs(userWithRole('administrator'))
        ->put(route('admin.design.tokens.update'), ['tokens' => $tokens])
        ->assertSessionHasErrors();
})->with([
    'css injection' => [['color.primary' => '#fff;} body{display:none']],
    'named colour' => [['color.primary' => 'red']],
    'unknown font' => [['font.body' => 'Comic Sans']],
    'raw token' => [['shadow.md' => 'none']],
    'unknown token' => [['color.evil' => '#000000']],
    'bad length' => [['radius.md' => '1em; x']],
]);

it('reports contrast warnings for unreadable palettes', function () {
    $warnings = app(DesignTokenService::class)->save(['color.body' => '#DDDDDD']);

    expect($warnings)->not->toBeEmpty()
        ->and($warnings[0])->toContain('Body text on white');
});

it('renders the design preview page with the token stylesheet', function () {
    $this->actingAs(userWithRole('editor'))
        ->get(route('admin.design.preview'))
        ->assertOk()
        ->assertSee('/storage/theme/tokens-', false);
});
