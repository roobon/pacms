<?php

use App\Auth\RolePermissionSynchronizer;
use App\Enums\WorkflowAction;
use App\Models\Page;
use App\Models\User;
use App\Services\Pages\PageService;
use App\Services\Publishing\PublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test bootstrap
|--------------------------------------------------------------------------
| Feature tests run against the MySQL database configured in phpunit.xml
| (pacms_testing) inside transactions, with roles/permissions seeded.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => app(RolePermissionSynchronizer::class)->sync())
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/**
 * A verified, active user with the given role. Staff get confirmed 2FA by default
 * because privileged roles are required to use it.
 */
/**
 * Form input for the admin page form.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pagePayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Our Programs',
        'slug' => '',
        'excerpt' => 'What we do.',
        'template' => 'default',
        'seo' => ['title' => '', 'description' => '', 'robots_index' => '1', 'robots_follow' => '1'],
    ], $overrides);
}

/**
 * Create a page as $user through the real service (paths, revisions, references).
 *
 * @param  array<string, mixed>  $data
 */
function makePage(User $user, array $data = []): Page
{
    return app(PageService::class)->create($user, array_merge(['title' => 'About us', 'template' => 'default'], $data));
}

/**
 * Create and publish a page (published by a super admin).
 *
 * @param  array<string, mixed>  $data
 */
function livePage(array $data = [], ?User $publisher = null): Page
{
    $publisher ??= userWithRole('super-admin');
    $page = makePage($publisher, $data);

    return app(PublishingService::class)->transition($page->fresh(), WorkflowAction::Publish, $publisher)->fresh();
}

/**
 * A real JPEG upload generated with GD (optionally with an injected EXIF segment).
 */
function fakeJpeg(string $name = 'photo.jpg', int $width = 1600, int $height = 1000, bool $withExif = false): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 10, 107, 102));
    ob_start();
    imagejpeg($image, null, 85);
    $jpeg = (string) ob_get_clean();

    if ($withExif) {
        // APP1 segment right after SOI containing an "Exif" header and a fake GPS marker.
        $payload = "Exif\0\0GPSLatitude=23.8103;GPSLongitude=90.4125";
        $jpeg = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2);
    }

    $path = tempnam(sys_get_temp_dir(), 'pacms').'.jpg';
    file_put_contents($path, $jpeg);

    return new UploadedFile($path, $name, 'image/jpeg', null, true);
}

function userWithRole(string $role, bool $twoFactor = true): User
{
    $factory = User::factory();

    if ($twoFactor) {
        $factory = $factory->withTwoFactor();
    }

    $user = $factory->create();
    $user->assignRole($role);

    return $user;
}
