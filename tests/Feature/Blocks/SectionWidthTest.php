<?php

use App\Cms\Blocks\BlockTreeValidator;
use App\Cms\Design\TokenCatalog;
use Illuminate\Validation\ValidationException;

it('offers sections the same widths as containers, with the same names', function () {
    $admin = userWithRole('administrator');
    $clean = app(BlockTreeValidator::class)->validate([['type' => 'section', 'layout' => ['container' => 'wide']]], $admin);
    expect($clean[0]['layout']['container'])->toBe('wide');

    expect(fn () => app(BlockTreeValidator::class)->validate([['type' => 'section', 'layout' => ['container' => 'huge']]], $admin))
        ->toThrow(ValidationException::class);

    $tokens = TokenCatalog::definitions();
    expect([$tokens['container.narrow']['label'], $tokens['container.xxl']['label'], $tokens['container.wide']['label']])->toBe(['Narrow', 'Site width', 'Wide']);
});
