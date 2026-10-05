<?php

use App\Models\Redirect;
use App\Services\Seo\RedirectService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('normalises paths', function () {
    expect(Redirect::normalizePath('About/Team/'))->toBe('/about/team')
        ->and(Redirect::normalizePath('/x?utm=1'))->toBe('/x')
        ->and(Redirect::normalizePath(''))->toBe('/');
});

it('collapses chains and never redirects a page to itself', function () {
    $service = app(RedirectService::class);

    $service->recordMove('/a', '/b');
    $service->recordMove('/b', '/c');

    expect(Redirect::where('source_path', '/a')->value('target_path'))->toBe('/c')
        ->and(Redirect::where('source_path', '/b')->value('target_path'))->toBe('/c');

    // Moving back to /a removes the redirect away from /a.
    $service->recordMove('/c', '/a');
    expect(Redirect::where('source_path', '/a')->exists())->toBeFalse()
        ->and(Redirect::where('source_path', '/c')->value('target_path'))->toBe('/a');
});
