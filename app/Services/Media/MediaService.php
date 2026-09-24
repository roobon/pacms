<?php

namespace App\Services\Media;

use App\Enums\MediaKind;
use App\Jobs\GenerateImageVariants;
use App\Models\Media;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogger;
use App\Services\Cache\CacheVersions;
use App\Services\Content\ContentReferenceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Media Library storage (CMS-ARCHITECTURE.md §14). Files are named by ULID,
 * images are re-encoded (metadata stripped) and get responsive WebP variants.
 */
class MediaService
{
    public function __construct(
        private readonly UploadGuard $guard,
        private readonly ImageProcessor $images,
        private readonly ContentReferenceService $references,
        private readonly ActivityLogger $logger,
        private readonly CacheVersions $cache,
    ) {}

    /**
     * @param  array<string, mixed>  $meta  alt, caption, description, credit, is_decorative
     */
    public function store(UploadedFile $file, User $user, array $meta = [], bool $private = false): Media
    {
        $info = $this->guard->inspect($file);
        $disk = $private ? config('pacms.media.private_disk') : config('pacms.media.disk');

        [$path, $width, $height, $size, $checksum] = $this->writeFile($file, $info, $disk);

        try {
            $media = DB::transaction(function () use ($file, $user, $meta, $info, $disk, $path, $width, $height, $size, $checksum) {
                $media = new Media;
                $media->fill(array_intersect_key($meta, array_flip(['alt', 'caption', 'description', 'credit', 'is_decorative'])));
                $media->forceFill([
                    'uuid' => (string) Str::ulid(),
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'mime_type' => $info['mime'],
                    'extension' => $info['extension'],
                    'kind' => $info['kind'],
                    'size' => $size,
                    'width' => $width,
                    'height' => $height,
                    'checksum_sha256' => $checksum,
                    'uploaded_by' => $user->getKey(),
                ])->save();

                return $media;
            });
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }

        $this->afterFileChange($media);
        $this->logger->log('media.uploaded', $media, ['kind' => $media->kind->value, 'size' => $media->size], $user, $media->original_name);

        return $media;
    }

    /**
     * Replace the file of an existing item: same ID and references, new URL (cache busting).
     */
    public function replace(Media $media, UploadedFile $file, User $user): Media
    {
        $info = $this->guard->inspect($file);

        if ($info['kind'] !== $media->kind) {
            throw ValidationException::withMessages(['file' => __('Replace a :kind with another :kind.', ['kind' => strtolower($media->kind->label())])]);
        }

        $oldFiles = $this->filesOf($media);
        [$path, $width, $height, $size, $checksum] = $this->writeFile($file, $info, $media->disk);

        $media->forceFill([
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $info['mime'],
            'extension' => $info['extension'],
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'checksum_sha256' => $checksum,
            'variants' => null,
        ])->save();

        Storage::disk($media->disk)->delete($oldFiles);

        $this->afterFileChange($media);
        $this->logger->log('media.replaced', $media, ['size' => $size], $user, $media->original_name);

        return $media;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateMetadata(Media $media, array $data, User $user): Media
    {
        $media->fill($data)->save();
        $this->cache->bump('media');
        $this->logger->log('media.updated', $media, ['fields' => array_keys($media->getChanges())], $user, $media->original_name);

        return $media;
    }

    /**
     * Permanently delete the item and its files. Blocked while in use unless forced.
     */
    public function delete(Media $media, User $user, bool $force = false): void
    {
        $usages = $this->references->usagesOf($media);

        if ($usages->isNotEmpty() && ! ($force && $user->can('media.force_delete'))) {
            throw ValidationException::withMessages([
                'media' => trans_choice('This file is used in :count place and cannot be deleted.|This file is used in :count places and cannot be deleted.', $usages->count(), ['count' => $usages->count()]),
            ]);
        }

        $files = $this->filesOf($media);

        DB::transaction(function () use ($media) {
            DB::table('content_references')->where('target_type', $media->getMorphClass())->where('target_id', $media->getKey())->delete();
            $media->terms()->detach();
            $media->delete();
        });

        Storage::disk($media->disk)->delete($files);
        $this->cache->bump('media');
        $this->logger->log('media.deleted', $media, ['forced' => $usages->isNotEmpty()], $user, $media->original_name);
    }

    /**
     * Create responsive WebP variants and a placeholder (queued after upload/replace).
     */
    public function generateVariants(Media $media): void
    {
        if ($media->kind !== MediaKind::Image) {
            return;
        }

        $disk = Storage::disk($media->disk);
        $source = $this->images->decode((string) $disk->get($media->path));
        $base = preg_replace('/\.[a-z0-9]+$/', '', $media->path);

        $widths = array_values(array_filter(config('pacms.media.variant_widths'), fn (int $w) => $w < (int) $media->width));
        if ($widths === [] || $media->extension !== 'webp') {
            $widths[] = (int) $media->width; // always offer a WebP at the original size
        }

        $variants = ['webp' => [], 'placeholder' => $this->images->placeholder($source)];
        foreach (array_unique($widths) as $width) {
            $path = "{$base}-{$width}.webp";
            $disk->put($path, $this->images->webpVariant($source, $width));
            $variants['webp'][$width] = $path;
        }

        // Remove variants of an earlier generation that are no longer referenced.
        $stale = array_diff((array) ($media->variants['webp'] ?? []), $variants['webp']);
        $disk->delete(array_values($stale));

        $media->forceFill(['variants' => $variants])->save();
        $this->cache->bump('media');
    }

    /**
     * @param  array{extension: string, mime: string, kind: MediaKind, width: ?int, height: ?int}  $info
     * @return array{0: string, 1: ?int, 2: ?int, 3: int, 4: string}
     */
    private function writeFile(UploadedFile $file, array $info, string $disk): array
    {
        $path = 'media/'.now()->format('Y/m').'/'.Str::lower((string) Str::ulid()).'.'.$info['extension'];
        $width = $info['width'];
        $height = $info['height'];

        if ($info['kind'] === MediaKind::Image) {
            try {
                $image = $this->images->decode((string) file_get_contents($file->getRealPath()));
                $binary = $this->images->reencode($image, $info['extension']);
                $width = $image->width();
                $height = $image->height();
            } catch (Throwable) {
                throw ValidationException::withMessages(['file' => __('This image could not be processed. Try saving it again as JPG or PNG.')]);
            }
            Storage::disk($disk)->put($path, $binary);
        } else {
            Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));
        }

        $stored = (string) Storage::disk($disk)->get($path);

        return [$path, $width, $height, strlen($stored), hash('sha256', $stored)];
    }

    /**
     * @return list<string>
     */
    private function filesOf(Media $media): array
    {
        return array_values(array_merge([$media->path], array_values((array) ($media->variants['webp'] ?? []))));
    }

    private function afterFileChange(Media $media): void
    {
        $this->cache->bump('media');

        if ($media->kind === MediaKind::Image) {
            GenerateImageVariants::dispatch($media->getKey());
        }
    }
}
