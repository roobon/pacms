<?php

namespace App\Cms\External\Providers;

use App\Cms\External\CachesImages;
use App\Cms\External\ExternalContentProvider;
use App\Cms\External\Facebook\FacebookImageCache;
use App\Cms\External\Facebook\GraphClient;
use App\Cms\External\FeedException;
use App\Models\ExternalSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Posts of the organisation's own Facebook Page (CMS-ARCHITECTURE.md §17): the official
 * Graph API with the Page's access token (pages_read_engagement, pages_read_user_content).
 * Published posts only; images are copied into the Media Library because Facebook's image
 * addresses expire.
 */
class FacebookProvider implements CachesImages, ExternalContentProvider
{
    public const FIELDS = 'id,message,created_time,permalink_url,full_picture,is_published';

    public function __construct(private readonly GraphClient $graph) {}

    public function key(): string
    {
        return 'facebook';
    }

    public function label(): string
    {
        return 'Facebook Page';
    }

    public function configRules(): array
    {
        return []; // the Page is chosen when connecting
    }

    public function config(array $input): array
    {
        return [];
    }

    public function fetch(ExternalSource $source): array
    {
        $credentials = (array) ($source->account->credentials ?? []);
        $pageId = (string) ($credentials['page_id'] ?? '');
        $token = (string) ($credentials['page_token'] ?? '');
        if ($pageId === '' || $token === '') {
            throw new FeedException(__('This source has no connected Facebook Page. Connect the Page again.'));
        }

        $data = $this->graph->get("{$pageId}/published_posts", ['fields' => self::FIELDS, 'limit' => min(100, max(1, $source->max_items))], $token);

        $items = [];
        foreach ((array) ($data['data'] ?? []) as $post) {
            if (! is_array($post) || ($post['is_published'] ?? true) === false || ! isset($post['id'])) {
                continue;
            }
            $message = trim((string) ($post['message'] ?? ''));
            $link = (string) ($post['permalink_url'] ?? '');
            $items[] = [
                'external_id' => mb_substr((string) $post['id'], 0, 255),
                'title' => $message === '' ? null : Str::limit((string) strtok($message, "\n"), 120),
                'link' => preg_match('#^https://([a-z0-9-]+\.)*facebook\.com/#i', $link) ? $link : null,
                'excerpt' => $message === '' ? null : Str::limit($message, 400),
                // Plain text with line breaks (posts have no HTML).
                'description' => $message === '' ? null : '<p>'.nl2br(e($message), false).'</p>',
                'author' => null,
                'category' => null,
                'image_url' => preg_match('#^https://#i', (string) ($post['full_picture'] ?? '')) ? (string) $post['full_picture'] : null,
                'published_at' => isset($post['created_time']) ? Carbon::parse((string) $post['created_time'])->utc() : null,
                'source_updated_at' => null,
            ];
        }

        return ['format' => 'facebook', 'title' => $source->account?->name, 'website' => $source->config['page_url'] ?? null, 'items' => $items];
    }

    public function cacheImages(ExternalSource $source): void
    {
        app(FacebookImageCache::class)->cache($source);
    }
}
