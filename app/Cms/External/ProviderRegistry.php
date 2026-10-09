<?php

namespace App\Cms\External;

use App\Cms\External\Providers\FeedProvider;

/**
 * The external providers this site offers (CMS-ARCHITECTURE.md §15.1).
 */
class ProviderRegistry
{
    /** @var array<string, ExternalContentProvider> */
    private array $providers = [];

    public function __construct()
    {
        foreach ([FeedProvider::class] as $class) {
            $this->register(app($class));
        }
    }

    public function register(ExternalContentProvider $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    /**
     * @return array<string, ExternalContentProvider>
     */
    public function all(): array
    {
        return $this->providers;
    }

    public function find(string $key): ?ExternalContentProvider
    {
        return $this->providers[$key] ?? null;
    }
}
