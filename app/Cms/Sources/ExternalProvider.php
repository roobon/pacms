<?php

namespace App\Cms\Sources;

/**
 * External content for blocks (CMS-ARCHITECTURE.md §15). Providers read items that were
 * synchronised into local storage by queued jobs — never by calling the remote service
 * during a visitor request. Real providers (RSS, Facebook…) arrive in Phase 10.
 */
interface ExternalProvider
{
    public function key(): string;

    /**
     * Whether $source (a configured source identifier) exists and is enabled.
     */
    public function hasSource(string $source): bool;

    /**
     * Normalised items for the source, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function items(string $source, int $limit): array;
}
