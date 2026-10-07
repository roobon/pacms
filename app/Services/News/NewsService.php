<?php

namespace App\Services\News;

use App\Enums\ContentStatus;
use App\Models\Media;
use App\Models\News;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogger;
use App\Services\Cache\CacheVersions;
use App\Services\Content\ContentReferenceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Minimal News management (Phase 4). Direct publishing: a published item is live and
 * editing it requires the publish permission (CMS-ARCHITECTURE.md §4.2).
 * The full module (body blocks, revisions, review workflow) is Phase 8.
 */
class NewsService
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly CacheVersions $cache,
        private readonly ContentReferenceService $references,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): News
    {
        return DB::transaction(function () use ($user, $data) {
            $news = new News;
            $news->fill(Arr::only($data, ['title', 'excerpt', 'body', 'featured_media_id', 'featured']));
            $news->slug = $this->uniqueSlug($data['slug'] ?? null, (string) $data['title']);
            $news->forceFill(['author_id' => $user->id, 'created_by' => $user->id, 'updated_by' => $user->id])->save();
            $news->syncTerms('news_category', (array) ($data['categories'] ?? []));

            $this->afterChange($news);
            $this->logger->log('news.created', $news, [], $user);

            return $news;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, News $news, array $data): News
    {
        if ($news->status === ContentStatus::Published && ! $user->can('news.publish')) {
            throw new AuthorizationException(__('Only publishers can change published news.'));
        }

        return DB::transaction(function () use ($user, $news, $data) {
            $news->fill(Arr::only($data, ['title', 'excerpt', 'body', 'featured_media_id', 'featured']));
            if (! empty($data['slug']) && $data['slug'] !== $news->slug) {
                $news->slug = $this->uniqueSlug($data['slug'], (string) $news->title, $news->id);
            }
            $news->updated_by = $user->id;
            $news->save();
            $news->syncTerms('news_category', (array) ($data['categories'] ?? []));

            $this->afterChange($news);
            $this->logger->log('news.updated', $news, ['fields' => array_keys($news->getChanges())], $user);

            return $news;
        });
    }

    public function publish(User $user, News $news): void
    {
        $this->authorizePublish($user);
        $news->forceFill(['status' => ContentStatus::Published, 'published_at' => $news->published_at ?? now(), 'updated_by' => $user->id])->save();
        $this->afterChange($news);
        $this->logger->log('news.published', $news, [], $user);
    }

    public function unpublish(User $user, News $news): void
    {
        $this->authorizePublish($user);
        $news->forceFill(['status' => ContentStatus::Draft, 'updated_by' => $user->id])->save();
        $this->afterChange($news);
        $this->logger->log('news.unpublished', $news, [], $user);
    }

    public function delete(User $user, News $news): void
    {
        $news->delete();
        $this->references->clear($news);
        $this->cache->bump('news');
        $this->logger->log('news.deleted', $news, [], $user);
    }

    private function authorizePublish(User $user): void
    {
        if (! $user->can('news.publish')) {
            throw new AuthorizationException(__('You are not allowed to publish news.'));
        }
    }

    private function afterChange(News $news): void
    {
        $references = [];
        if ($news->featured_media_id && ($media = Media::query()->find($news->featured_media_id))) {
            $references[] = ['target' => $media, 'context' => 'featured_image'];
        }
        $this->references->sync($news, $references);
        $this->cache->bump('news');
    }

    private function uniqueSlug(?string $slug, string $title, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($slug ?: $title) ?: 'news', 180, '');
        $candidate = $base;
        $i = 2;

        while (News::withTrashed()->where('slug', $candidate)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $candidate = "{$base}-{$i}";
            $i++;
        }

        return $candidate;
    }
}
