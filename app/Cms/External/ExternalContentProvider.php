<?php

namespace App\Cms\External;

use App\Models\ExternalSource;
use Illuminate\Validation\ValidationException;

/**
 * A kind of external source (CMS-ARCHITECTURE.md §15.1): feeds now, Facebook in 10B. A
 * provider only reads the remote service; storing, scheduling and showing items is shared.
 */
interface ExternalContentProvider
{
    /** Stored in external_sources.provider, e.g. "feed". */
    public function key(): string;

    public function label(): string;

    /**
     * Validation rules for the provider's settings (config.*) in the admin form.
     *
     * @return array<string, mixed>
     */
    public function configRules(): array;

    /**
     * Clean settings from validated input.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function config(array $input): array;

    /**
     * Read the source's current items from the remote service (never called during a
     * visitor request).
     *
     * @return array{format: string|null, title: string|null, website: string|null, items: list<array<string, mixed>>}
     *
     * @throws FeedException|\RuntimeException with a message for admins
     */
    public function fetch(ExternalSource $source): array;
}
