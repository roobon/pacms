<?php

namespace App\Services\External;

use App\Cms\External\Facebook\GraphClient;
use App\Cms\External\FeedException;
use App\Models\ExternalProviderAccount;
use App\Models\ExternalSource;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Connecting the organisation's own Facebook Page (CMS-ARCHITECTURE.md §17.2): with the Meta
 * app (OAuth: user token → long-lived → the Page's token), or by pasting a Page access token
 * (e.g. a Business Manager system-user token, which does not expire). Either way PACMS keeps
 * only the Page token, encrypted, and creates the Page's external source.
 */
class FacebookConnectService
{
    public function __construct(
        private readonly GraphClient $graph,
        private readonly ExternalSyncService $sync,
        private readonly ActivityLogger $logger,
    ) {}

    public function appConfigured(): bool
    {
        return filled(config('services.meta.app_id')) && filled(config('services.meta.app_secret'));
    }

    /**
     * OAuth callback: the Pages the person may read, with their Page tokens.
     *
     * @return list<array{id: string, name: string, link: string|null, token: string}>
     *
     * @throws FeedException
     */
    public function pagesFromCode(string $code, string $redirect): array
    {
        $app = ['client_id' => (string) config('services.meta.app_id'), 'client_secret' => (string) config('services.meta.app_secret')];
        $short = $this->graph->get('oauth/access_token', $app + ['redirect_uri' => $redirect, 'code' => $code], null);
        // A long-lived user token gives Page tokens that do not expire.
        $long = $this->graph->get('oauth/access_token', $app + ['grant_type' => 'fb_exchange_token', 'fb_exchange_token' => (string) ($short['access_token'] ?? '')], null);
        $accounts = $this->graph->get('me/accounts', ['fields' => 'id,name,link,access_token', 'limit' => 100], (string) ($long['access_token'] ?? ''));

        $pages = [];
        foreach ((array) ($accounts['data'] ?? []) as $page) {
            if (isset($page['id'], $page['access_token'])) {
                $pages[] = ['id' => (string) $page['id'], 'name' => (string) ($page['name'] ?? $page['id']), 'link' => $page['link'] ?? null, 'token' => (string) $page['access_token']];
            }
        }
        if ($pages === []) {
            throw new FeedException(__('Facebook shows no Pages you manage for this app. Check that you chose the Page and granted the permissions.'));
        }

        return $pages;
    }

    /**
     * Check a Page token and connect the Page (a new source, or the existing one updated).
     *
     * @throws FeedException when Facebook does not accept the token for this Page
     */
    public function connect(User $user, string $pageId, string $token): ExternalSource
    {
        $page = $this->graph->get($pageId, ['fields' => 'id,name,link'], $token);
        if ((string) ($page['id'] ?? '') !== $pageId) {
            throw new FeedException(__('This token does not belong to that Page.'));
        }
        $name = (string) ($page['name'] ?? 'Facebook Page');

        $account = ExternalProviderAccount::query()->where('provider', 'facebook')->get()->first(fn ($a) => (string) ($a->credentials['page_id'] ?? '') === $pageId)
            ?? new ExternalProviderAccount(['provider' => 'facebook', 'created_by' => $user->id]);
        $account->forceFill([
            'name' => $name,
            'credentials' => ['page_id' => $pageId, 'page_token' => $token],
            'token_expires_at' => $this->expiry($token),
            'status' => 'active',
            'last_verified_at' => now(),
        ])->save();

        $source = ExternalSource::query()->where('provider', 'facebook')->where('account_id', $account->id)->first();
        if ($source === null) {
            $source = new ExternalSource(['name' => $name.' (Facebook)', 'sync_interval_minutes' => 60, 'max_items' => 30, 'status' => 'enabled']);
            $source->provider = 'facebook';
            $source->slug = $this->slug('facebook-'.$name);
            $source->forceFill(['created_by' => $user->id]);
        }
        $source->forceFill([
            'account_id' => $account->id,
            'config' => ['page_id' => $pageId, 'page_url' => is_string($page['link'] ?? null) ? $page['link'] : null],
            'updated_by' => $user->id,
            'next_sync_at' => now(),
        ])->save();
        $this->logger->log('facebook.connected', $source, ['page_id' => $pageId], $user, $name);

        $this->sync->sync($source, 'manual');

        return $source->fresh() ?? $source;
    }

    /**
     * When the token expires, if the Meta app can tell (null: does not expire, or unknown).
     */
    private function expiry(string $token): ?Carbon
    {
        if (! $this->appConfigured()) {
            return null;
        }
        try {
            $info = $this->graph->get('debug_token', ['input_token' => $token], config('services.meta.app_id').'|'.config('services.meta.app_secret'));
            $expires = (int) ($info['data']['expires_at'] ?? 0);

            return $expires > 0 ? Carbon::createFromTimestamp($expires) : null;
        } catch (FeedException) {
            return null;
        }
    }

    private function slug(string $wanted): string
    {
        $base = Str::limit(Str::slug($wanted) ?: 'facebook', 80, '');
        $slug = $base;
        for ($n = 2; ExternalSource::withTrashed()->where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
