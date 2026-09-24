<?php

use App\Support\Color\Color;

it('computes WCAG contrast ratios', function () {
    expect(round(Color::fromHex('#000000')->contrastWith(Color::fromHex('#FFFFFF')), 2))->toBe(21.0)
        ->and(round(Color::fromHex('#0A6B66')->contrastWith(Color::fromHex('#FFFFFF')), 2))->toBe(6.35)
        ->and(round(Color::fromHex('#F2A93B')->contrastWith(Color::fromHex('#FFFFFF')), 2))->toBe(2.0);
});

it('expands short hex and formats rgb triplets', function () {
    expect(Color::fromHex('#fff')->toHex())->toBe('#FFFFFF')
        ->and(Color::fromHex('#0A6B66')->toRgbTriplet())->toBe('10, 107, 102');
});

it('picks the more readable text colour', function () {
    $dark = Color::fromHex('#0F1B2D');
    $light = Color::fromHex('#FFFFFF');

    expect(Color::fromHex('#F2A93B')->readableText($dark, $light)->toHex())->toBe('#0F1B2D')
        ->and(Color::fromHex('#0A6B66')->readableText($dark, $light)->toHex())->toBe('#FFFFFF');
});

it('rejects invalid colours', function () {
    Color::fromHex('red');
})->throws(InvalidArgumentException::class);
