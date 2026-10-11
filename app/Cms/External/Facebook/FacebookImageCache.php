<?php

namespace App\Cms\External\Facebook;

use App\Models\ExternalItem;
use App\Models\ExternalSource;
use App\Models\Media;
use App\Models\Term;
use App\Models\User;
use App\Services\Media\MediaService;
use App\Support\Http\SafeHttpClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Copies Facebook post images into the Media Library (category "Facebook cache"), because
 * the signed image addresses Facebook sends expire. Items whose image cannot be copied keep
 * the address and are tried again next sync.
 */
class FacebookImageCache
{
    public const CATEGORY = 'Facebook cache';

    public const PER_SYNC = 20;

    public function cache(ExternalSource $source): void
    {
        $user = $this->owner($source);
        if ($user === null) {
            return;
        }
        $term = Term::query()->firstOrCreate(['taxonomy' => 'media_category', 'slug' => 'facebook-cache'], ['name' => self::CATEGORY]);

        $items = ExternalItem::query()->where('external_source_id', $source->id)->whereNull('image_media_id')->whereNotNull('image_url')->limit(self::PER_SYNC)->get();
        foreach ($items as $item) {
            try {
                $file = app(SafeHttpClient::class)->download((string) $item->image_url, 10 * 1024 * 1024, 20);
                try {
                    $upload = new UploadedFile($file['path'], 'facebook-'.preg_replace('/[^0-9_]/', '', $item->external_id).'.jpg', $file['mime'], null, true);
                    $media = app(MediaService::class)->findDuplicate($upload) ?? app(MediaService::class)->store($upload, $user, ['credit' => 'Facebook: '.$source->name]);
                    $media->terms()->syncWithoutDetaching([$term->id]);
                    $item->forceFill(['image_media_id' => $media->id])->save();
                } finally {
                    @unlink($file['path']);
                }
            } catch (Throwable $e) {
                Log::info('Facebook image not cached', ['item' => $item->id, 'reason' => $e->getMessage()]);
            }
        }
    }

    /**
     * Remove cached images of items that were removed, unless used elsewhere.
     *
     * @param  list<int>  $mediaIds
     */
    public function forget(ExternalSource $source, array $mediaIds): void
    {
        $user = $this->owner($source);
        foreach (Media::query()->whereKey($mediaIds)->whereHas('terms', fn ($q) => $q->where('taxonomy', 'media_category')->where('slug', 'facebook-cache'))->get() as $media) {
            if (ExternalItem::query()->where('image_media_id', $media->id)->exists() || $user === null) {
                continue;
            }
            try {
                app(MediaService::class)->delete($media, $user);
            } catch (Throwable) {
                // Used somewhere (e.g. an editor placed it on a page): keep it.
            }
        }
    }

    /** Uploads are recorded as the person who added the source (or the first super admin). */
    private function owner(ExternalSource $source): ?User
    {
        return User::query()->find($source->getAttribute('created_by')) ?? User::query()->role('super-admin')->orderBy('id')->first();
    }
}
