<?php

use App\Enums\MediaKind;
use App\Models\ActivityLog;
use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('uploads, re-encodes and creates responsive variants for images', function () {
    $user = userWithRole('contributor', twoFactor: false);

    $this->actingAs($user)->post(route('admin.media.store'), ['files' => [fakeJpeg('Beach Photo.JPG', 2400, 1600, withExif: true)]])
        ->assertRedirect(route('admin.media.index'));

    $media = Media::firstOrFail();
    expect($media->kind)->toBe(MediaKind::Image)
        ->and($media->path)->toMatch('#^media/\d{4}/\d{2}/[0-9a-z]{26}\.jpg$#')
        ->and($media->original_name)->toBe('Beach Photo.JPG')
        ->and($media->width)->toBe(2400)
        ->and(array_keys($media->variants['webp']))->toBe([320, 640, 960, 1280, 1920, 2400])
        ->and($media->variants['placeholder'])->toStartWith('data:image/webp;base64,');

    $stored = Storage::disk('public')->get($media->path);
    expect($stored)->not->toContain('Exif')->not->toContain('GPSLatitude');

    foreach ($media->variants['webp'] as $path) {
        Storage::disk('public')->assertExists($path);
    }

    expect(ActivityLog::where('action', 'media.uploaded')->exists())->toBeTrue();
});

it('rejects dangerous or mismatched files', function (UploadedFile $file) {
    $this->actingAs(userWithRole('editor'))
        ->post(route('admin.media.store'), ['files' => [$file]])
        ->assertSessionHasErrors('file');

    expect(Media::count())->toBe(0);
})->with([
    'php disguised as jpg' => fn () => UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo "pwned"; ?>'),
    'double extension' => fn () => UploadedFile::fake()->createWithContent('photo.php.jpg', (string) fakeJpeg()->getContent()),
    'svg' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    'html' => fn () => UploadedFile::fake()->createWithContent('page.html', '<html><script>alert(1)</script></html>'),
    'exe' => fn () => UploadedFile::fake()->createWithContent('setup.exe', 'MZ'.str_repeat("\0", 100)),
]);

it('rejects images with excessive dimensions (decompression bomb guard)', function () {
    config(['pacms.media.max_dimension' => 1000]);

    $this->actingAs(userWithRole('editor'))
        ->post(route('admin.media.store'), ['files' => [fakeJpeg('huge.jpg', 1500, 200)]])
        ->assertSessionHasErrors('file');
});

it('stores documents as-is and never generates variants for them', function () {
    $pdf = UploadedFile::fake()->createWithContent('report.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

    $this->actingAs(userWithRole('editor'))->post(route('admin.media.store'), ['files' => [$pdf]])->assertSessionHasNoErrors();

    $media = Media::firstOrFail();
    expect($media->kind)->toBe(MediaKind::Document)->and($media->variants)->toBeNull();
});

it('requires alt text for images unless marked decorative', function () {
    $editor = userWithRole('editor');
    $this->actingAs($editor)->post(route('admin.media.store'), ['files' => [fakeJpeg()]]);
    $media = Media::firstOrFail();

    $this->actingAs($editor)->put(route('admin.media.update', $media), ['alt' => ''])->assertSessionHasErrors('alt');
    $this->actingAs($editor)->put(route('admin.media.update', $media), ['alt' => 'Volunteers planting trees', 'credit' => 'Photo: A. Rahman'])->assertSessionHasNoErrors();
    $this->actingAs($editor)->put(route('admin.media.update', $media), ['alt' => '', 'is_decorative' => '1'])->assertSessionHasNoErrors();

    expect($media->fresh()->is_decorative)->toBeTrue();
});

it('blocks deleting media that is in use unless forced by an allowed user', function () {
    $editor = userWithRole('editor');
    $this->actingAs($editor)->post(route('admin.media.store'), ['files' => [fakeJpeg()]]);
    $media = Media::firstOrFail();
    makePage($editor, ['title' => 'Uses image', 'featured_media_id' => $media->id]);

    $this->actingAs($editor)->get(route('admin.media.edit', $media))->assertSee('Uses image');
    $this->actingAs($editor)->delete(route('admin.media.destroy', $media))->assertSessionHasErrors('media');
    expect(Media::count())->toBe(1);

    // Editors do not hold media.force_delete; administrators do.
    $this->actingAs($editor)->delete(route('admin.media.destroy', $media), ['force' => 1])->assertSessionHasErrors('media');

    $files = array_merge([$media->path], array_values($media->variants['webp']));
    $this->actingAs(userWithRole('administrator'))->delete(route('admin.media.destroy', $media), ['force' => 1])->assertRedirect(route('admin.media.index'));

    expect(Media::count())->toBe(0);
    foreach ($files as $path) {
        Storage::disk('public')->assertMissing($path);
    }
});

it('replaces a file while keeping the item and its references', function () {
    $editor = userWithRole('editor');
    $this->actingAs($editor)->post(route('admin.media.store'), ['files' => [fakeJpeg('first.jpg', 800, 600)]]);
    $media = Media::firstOrFail();
    $oldPath = $media->path;

    $this->actingAs($editor)->post(route('admin.media.replace', $media), ['file' => fakeJpeg('second.jpg', 1200, 800)])->assertSessionHasNoErrors();

    $media->refresh();
    expect($media->path)->not->toBe($oldPath)->and($media->width)->toBe(1200)->and($media->original_name)->toBe('second.jpg');
    Storage::disk('public')->assertMissing($oldPath);

    $pdf = UploadedFile::fake()->createWithContent('doc.pdf', "%PDF-1.4\n%%EOF");
    $this->actingAs($editor)->post(route('admin.media.replace', $media), ['file' => $pdf])->assertSessionHasErrors('file');
});

it('keeps private files out of public storage and serves them only to staff', function () {
    $editor = userWithRole('editor');
    $this->actingAs($editor)->post(route('admin.media.store'), ['files' => [UploadedFile::fake()->createWithContent('minutes.txt', 'Board minutes')], 'private' => 1]);

    $media = Media::firstOrFail();
    expect($media->isPublic())->toBeFalse()->and($media->url())->toBeNull();
    Storage::disk('public')->assertMissing($media->path);

    $this->actingAs($editor)->get(route('admin.media.download', $media))->assertOk();
    $this->actingAs(userWithRole('registered-user', twoFactor: false))->get(route('admin.media.download', $media))->assertForbidden();
});

it('enforces media permissions', function () {
    $this->actingAs(userWithRole('moderator', twoFactor: false))->get(route('admin.media.index'))->assertForbidden();
    $this->actingAs(userWithRole('moderator', twoFactor: false))->post(route('admin.media.store'), ['files' => [fakeJpeg()]])->assertForbidden();

    $contributor = userWithRole('contributor', twoFactor: false);
    $this->actingAs($contributor)->post(route('admin.media.store'), ['files' => [fakeJpeg()]])->assertSessionHasNoErrors();
    $this->actingAs($contributor)->delete(route('admin.media.destroy', Media::firstOrFail()))->assertForbidden();
});

it('serves the media picker API with search and upload', function () {
    $editor = userWithRole('editor');
    $this->actingAs($editor)->postJson(route('admin.api.media.store'), ['file' => fakeJpeg('team-photo.jpg'), 'alt' => 'The team'])
        ->assertCreated()
        ->assertJsonPath('data.alt', 'The team')
        ->assertJsonPath('data.kind', 'image');

    $this->actingAs($editor)->getJson(route('admin.api.media.index', ['q' => 'team', 'kind' => 'image']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'team-photo.jpg');

    $this->actingAs($editor)->postJson(route('admin.api.media.store'), ['file' => UploadedFile::fake()->createWithContent('x.jpg', 'not an image')])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed');
});

it('exposes responsive image data for featured images in the page payload', function () {
    $editor = userWithRole('editor');
    $this->actingAs($editor)->postJson(route('admin.api.media.store'), ['file' => fakeJpeg('hero.jpg', 2000, 1000), 'alt' => 'Hero']);
    $media = Media::firstOrFail();

    livePage(['title' => 'With image', 'featured_media_id' => $media->id]);

    $image = $this->getJson('/api/v1/pages/with-image')->assertOk()->json('data.featured_image');
    expect($image['alt'])->toBe('Hero')
        ->and($image['width'])->toBe(2000)
        ->and($image['srcset'])->toContain('320w')->toContain('1920w')
        ->and($image['src'])->toStartWith('/storage/media/');

    $this->get('/with-image')->assertSee('<meta property="og:image" content="http://pacms.test/storage/media/', false);
});
