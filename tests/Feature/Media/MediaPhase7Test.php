<?php

use App\Models\ActivityLog;
use App\Models\ContentReference;
use App\Models\Media;
use App\Models\Term;
use App\Services\Media\MediaService;
use App\Services\Settings\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

function svgFile(string $content, string $name = 'logo.svg'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'svg').'.svg';
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, 'image/svg+xml', null, true);
}

it('does not store the same file twice unless asked', function () {
    $editor = userWithRole('editor');
    $jpeg = fakeJpeg('first.jpg');
    $copy = new UploadedFile($jpeg->getRealPath(), 'second.jpg', 'image/jpeg', null, true);

    $this->actingAs($editor)->post(route('admin.media.store'), ['files' => [$jpeg]]);
    $this->actingAs($editor)->post(route('admin.media.store'), ['files' => [$copy]])
        ->assertSessionHas('duplicates', fn ($d) => $d[0]['name'] === 'second.jpg' && $d[0]['existing'] === 'first.jpg');
    expect(Media::count())->toBe(1);

    // The picker gets the existing item back.
    $this->actingAs($editor)->post(route('admin.api.media.store'), ['file' => $copy, 'alt' => 'x'])
        ->assertOk()
        ->assertJsonPath('meta.duplicate', true)
        ->assertJsonPath('data.id', Media::first()->id);

    $this->actingAs($editor)->post(route('admin.media.store'), ['files' => [$copy], 'allow_duplicates' => '1']);
    expect(Media::count())->toBe(2);
});

it('filters by use, alt text, visibility and tag, and sorts', function () {
    $editor = userWithRole('editor');
    $service = app(MediaService::class);
    $used = $service->store(fakeJpeg('used.jpg', 400, 300), $editor, ['alt' => 'Used photo']);
    $noAlt = $service->store(fakeJpeg('no-alt.jpg', 1600, 1000), $editor);
    $private = $service->store(fakeJpeg('secret.jpg', 300, 200), $editor, ['alt' => 'Secret'], private: true);
    ContentReference::query()->create(['owner_type' => 'page', 'owner_id' => livePage()->id, 'target_type' => 'media', 'target_id' => $used->id, 'context' => 'featured_image']);
    $tag = Term::query()->create(['taxonomy' => 'tag', 'name' => 'Forest', 'slug' => 'forest']);
    $noAlt->terms()->attach($tag->id);

    $names = fn (array $query) => collect($this->actingAs($editor)->getJson(route('admin.api.media.index', $query))->json('data'))->pluck('name')->all();

    expect($names(['usage' => 'used']))->toBe(['used.jpg'])
        ->and($names(['usage' => 'unused', 'sort' => 'name']))->toBe(['no-alt.jpg', 'secret.jpg'])
        ->and($names(['needs_alt' => 1]))->toBe(['no-alt.jpg'])
        ->and($names(['visibility' => 'private']))->toBe(['secret.jpg'])
        ->and($names(['tag' => $tag->id]))->toBe(['no-alt.jpg'])
        ->and($names(['q' => 'forest']))->toBe(['no-alt.jpg'])
        ->and($names(['sort' => 'largest'])[0])->toBe('no-alt.jpg');
});

it('runs bulk actions and skips what cannot change', function () {
    $editor = userWithRole('editor');
    $service = app(MediaService::class);
    $a = $service->store(fakeJpeg('a.jpg', 300, 200), $editor, ['alt' => 'A']);
    $b = $service->store(fakeJpeg('b.jpg', 310, 200), $editor, ['alt' => 'B']);
    ContentReference::query()->create(['owner_type' => 'page', 'owner_id' => livePage()->id, 'target_type' => 'media', 'target_id' => $a->id, 'context' => 'block_content']);
    $category = Term::query()->create(['taxonomy' => 'media_category', 'name' => 'Events', 'slug' => 'events']);

    $this->actingAs($editor)->post(route('admin.media.bulk'), ['ids' => [$a->id, $b->id], 'action' => 'category', 'term_id' => $category->id])
        ->assertSessionHas('success', '2 files updated.');
    expect($a->terms()->count())->toBe(1);

    // A used file is neither made private nor deleted.
    $this->actingAs($editor)->post(route('admin.media.bulk'), ['ids' => [$a->id, $b->id], 'action' => 'private'])
        ->assertSessionHas('skipped', fn ($s) => count($s) === 1 && str_contains($s[0], 'a.jpg'));
    expect($a->fresh()->isPublic())->toBeTrue()
        ->and($b->fresh()->isPublic())->toBeFalse();
    Storage::disk('local')->assertExists($b->fresh()->path);
    Storage::disk('public')->assertMissing($b->fresh()->path);

    $this->actingAs($editor)->post(route('admin.media.bulk'), ['ids' => [$a->id, $b->id], 'action' => 'delete']);
    expect(Media::query()->pluck('id')->all())->toBe([$a->id]);

    $this->actingAs(userWithRole('contributor', twoFactor: false))->post(route('admin.media.bulk'), ['ids' => [$a->id], 'action' => 'delete'])->assertForbidden();
});

it('previews private files only for staff, with safe headers', function () {
    $editor = userWithRole('editor');
    $media = app(MediaService::class)->store(fakeJpeg('private.jpg', 300, 200), $editor, ['alt' => 'P'], private: true);

    $response = $this->actingAs($editor)->get(route('admin.media.file', $media))->assertOk();
    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and(implode(' ', $response->headers->all('content-security-policy')))->toContain('sandbox')
        ->and($media->url())->toBeNull();

    auth()->logout();
    $this->get(route('admin.media.file', $media))->assertRedirect();
});

it('refuses SVG unless allowed and permitted, and always cleans it', function () {
    $evil = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 40" onload="alert(1)">'
        .'<script>alert(2)</script><a href="javascript:alert(3)"><rect width="10" height="10"/></a>'
        .'<foreignObject><iframe src="https://evil.example"/></foreignObject><image href="https://evil.example/x.png"/>'
        .'<circle cx="20" cy="20" r="10" fill="#0A6B66"/></svg>';
    $admin = userWithRole('administrator');

    // Off by default.
    $this->actingAs($admin)->post(route('admin.media.store'), ['files' => [svgFile($evil)]])->assertSessionHasErrors('file');

    app(SettingsService::class)->set('media', ['allow_svg' => true]);

    // On, but editors lack media.upload_svg.
    $this->actingAs(userWithRole('editor'))->post(route('admin.media.store'), ['files' => [svgFile($evil)]])->assertSessionHasErrors('file');

    $this->actingAs($admin)->post(route('admin.media.store'), ['files' => [svgFile($evil)]])->assertSessionHasNoErrors();
    $media = Media::query()->where('extension', 'svg')->firstOrFail();
    $stored = Storage::disk('public')->get($media->path);

    expect($stored)->toContain('<circle')
        ->not->toContain('<script')->not->toContain('onload')->not->toContain('javascript:')
        ->not->toContain('foreignObject')->not->toContain('evil.example')
        ->and($media->width)->toBe(120)
        ->and($media->height)->toBe(40)
        ->and($media->variants)->toBeNull()
        ->and($media->toImageArray()['srcset'])->toBeNull()
        ->and(ActivityLog::where('action', 'media.svg_uploaded')->exists())->toBeTrue();
});

it('lets administrators switch SVG uploads on in the settings', function () {
    $admin = userWithRole('administrator');

    $this->actingAs($admin)->put(route('admin.settings.general.update'), [
        'name' => 'Test org', 'timezone' => 'Asia/Dhaka', 'allow_svg' => '1',
    ])->assertSessionHasNoErrors();

    expect(app(SettingsService::class)->get('media', 'allow_svg'))->toBeTrue()
        ->and(ActivityLog::where('action', 'settings.svg_enabled')->exists())->toBeTrue();
});

it('regenerates image variants from the command line', function () {
    $media = app(MediaService::class)->store(fakeJpeg('regen.jpg', 700, 400), userWithRole('editor'), ['alt' => 'R']);
    $media->forceFill(['variants' => null])->save();

    $this->artisan('pacms:media:regenerate', ['--missing' => true])->assertSuccessful();

    expect(array_keys($media->fresh()->variants['webp']))->toBe([320, 640, 700]);
});
