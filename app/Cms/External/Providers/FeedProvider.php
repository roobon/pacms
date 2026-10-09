<?php

namespace App\Cms\External\Providers;

use App\Cms\External\ExternalContentProvider;
use App\Cms\External\FeedException;
use App\Cms\External\FeedParser;
use App\Models\ExternalSource;
use App\Support\Http\DownloadFailedException;
use App\Support\Http\SafeHttpClient;
use App\Support\Http\UnsafeUrlException;

/**
 * Feeds of other websites (D-15): JSON Feed first, RSS and Atom as the fallback (news
 * sites, WordPress, YouTube channels and playlists). Downloaded with the SSRF-guarded
 * client: public addresses only, 5 MB, 15 seconds.
 */
class FeedProvider implements ExternalContentProvider
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public function __construct(private readonly FeedParser $parser) {}

    public function key(): string
    {
        return 'feed';
    }

    public function label(): string
    {
        return 'Feed (JSON Feed, RSS or Atom)';
    }

    public function configRules(): array
    {
        return ['config.feed_url' => ['required', 'url:http,https', 'max:2048']];
    }

    public function config(array $input): array
    {
        return ['feed_url' => trim((string) ($input['feed_url'] ?? ''))];
    }

    public function fetch(ExternalSource $source): array
    {
        $url = (string) ($source->config['feed_url'] ?? '');

        try {
            // Resolved per fetch: the registry lives for the whole process (queue workers).
            $response = app(SafeHttpClient::class)->fetch($url, self::MAX_BYTES, 15);
        } catch (UnsafeUrlException|DownloadFailedException $e) {
            throw new FeedException($e->getMessage(), previous: $e);
        }

        return $this->parser->parse($response['body'], $response['url'], $response['mime']);
    }
}
