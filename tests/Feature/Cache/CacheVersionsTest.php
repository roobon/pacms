<?php

use App\Services\Cache\CacheVersions;

it('keeps cache keys short however many content types exist', function () {
    $versions = app(CacheVersions::class);
    $groups = array_map(fn ($i) => "a_rather_long_content_type_key_{$i}", range(1, 40));

    $before = $versions->fingerprint(...$groups);
    expect(strlen($before))->toBe(40);

    // A changed version still changes the key.
    $versions->bump('a_rather_long_content_type_key_7');
    expect($versions->fingerprint(...$groups))->not->toBe($before);
});
