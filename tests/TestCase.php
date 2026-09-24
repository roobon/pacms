<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Generated files (design-token stylesheets …) never touch real storage in tests.
        Storage::fake('public');

        // Tests run against a real MySQL test database but must not need a Vite build.
        $this->withoutVite();
    }
}
