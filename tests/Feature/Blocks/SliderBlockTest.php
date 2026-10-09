<?php

use App\Cms\Blocks\BlockPayloadResolver;
use App\Cms\Blocks\BlockTreeValidator;
use App\Services\Media\MediaService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake((string) config('pacms.media.disk'));
});

function sliderTree(array $slides): array
{
    return [['type' => 'slider', 'content' => ['autoplay' => true, 'interval' => 6], 'children' => $slides]];
}

it('builds a slider of slides with background images', function () {
    $admin = userWithRole('super-admin');
    $photo = app(MediaService::class)->store(fakeJpeg('coast.jpg'), $admin, ['alt' => 'Coast']);

    $clean = app(BlockTreeValidator::class)->validate(sliderTree([
        ['type' => 'slide', 'content' => ['image' => ['$media' => $photo->id], 'shade' => 'dark'], 'children' => [['type' => 'heading', 'content' => ['text' => 'One', 'level' => '1']]]],
        ['type' => 'slide', 'children' => [['type' => 'heading', 'content' => ['text' => 'Two', 'level' => '2']]]],
    ]), $admin);
    $payload = app(BlockPayloadResolver::class)->resolve($clean)[0];

    expect($payload['type'])->toBe('slider')
        ->and($payload['children'])->toHaveCount(2)
        ->and($payload['children'][0]['content']['image']['src'])->toContain('/storage/')
        ->and($payload['content']['interval'])->toBe(6);
});

it('keeps slides inside sliders, and sliders at the top of the page', function () {
    $admin = userWithRole('super-admin');
    $validator = app(BlockTreeValidator::class);

    // A slider holds slides only.
    expect(fn () => $validator->validate(sliderTree([['type' => 'heading', 'content' => ['text' => 'Loose', 'level' => '2']]]), $admin))
        ->toThrow(ValidationException::class);
    // A slide never stands alone.
    expect(fn () => $validator->validate([['type' => 'slide']], $admin))->toThrow(ValidationException::class);
    // A slider is not placed inside a section.
    expect(fn () => $validator->validate([['type' => 'section', 'children' => sliderTree([['type' => 'slide']])]], $admin))
        ->toThrow(ValidationException::class);
});
