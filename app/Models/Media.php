<?php

namespace App\Models;

use App\Enums\MediaKind;
use App\Models\Concerns\HasTerms;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A Media Library item. Files are stored under a generated ULID name; the original
 * name is metadata only. Created and replaced exclusively through MediaService.
 *
 * @property MediaKind $kind
 * @property array<string, mixed>|null $variants
 * @property array{x: float, y: float}|null $focal_point
 */
class Media extends Model
{
    use HasTerms;

    protected $table = 'media';

    /**
     * Editable metadata only; file attributes are set by MediaService.
     *
     * @var list<string>
     */
    protected $fillable = ['alt', 'caption', 'description', 'credit', 'is_decorative', 'focal_point'];

    protected function casts(): array
    {
        return [
            'kind' => MediaKind::class,
            'variants' => 'array',
            'focal_point' => 'array',
            'is_decorative' => 'boolean',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    public function isImage(): bool
    {
        return $this->kind === MediaKind::Image;
    }

    public function isPublic(): bool
    {
        return $this->disk === config('pacms.media.disk');
    }

    /**
     * Root-relative public URL, or null for private files (served through a controller).
     */
    public function url(): ?string
    {
        return $this->isPublic() ? $this->publicUrl($this->path) : null;
    }

    /**
     * Largest-first list of generated WebP variants: width => URL.
     *
     * @return array<int, string>
     */
    public function variantUrls(): array
    {
        if (! $this->isPublic()) {
            return [];
        }

        $urls = [];
        foreach ((array) ($this->variants['webp'] ?? []) as $width => $path) {
            $urls[(int) $width] = $this->publicUrl((string) $path);
        }
        krsort($urls);

        return $urls;
    }

    /**
     * Best URL for a thumbnail of roughly the given width (falls back to the original).
     */
    public function thumbnailUrl(int $width = 320): ?string
    {
        $variants = $this->variantUrls();
        ksort($variants);
        foreach ($variants as $variantWidth => $url) {
            if ($variantWidth >= $width) {
                return $url;
            }
        }

        return $variants !== [] ? end($variants) : $this->url();
    }

    /**
     * Responsive image data for the public API (CMS-ARCHITECTURE.md §14).
     *
     * @return array<string, mixed>|null
     */
    public function toImageArray(string $sizes = '100vw'): ?array
    {
        if (! $this->isImage() || ! $this->isPublic()) {
            return null;
        }

        $variants = $this->variantUrls();
        ksort($variants);

        return [
            'id' => $this->id,
            'src' => $this->url(),
            'srcset' => $variants === [] ? null : collect($variants)->map(fn ($url, $width) => "{$url} {$width}w")->implode(', '),
            'sizes' => $variants === [] ? null : $sizes,
            'width' => $this->width,
            'height' => $this->height,
            'alt' => $this->is_decorative ? '' : (string) $this->alt,
            'placeholder' => $this->variants['placeholder'] ?? null,
            'focal_point' => $this->focal_point,
        ];
    }

    public function humanSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->size;
        $unit = 0;
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, $unit === 0 ? 0 : 1).' '.$units[$unit];
    }

    private function publicUrl(string $path): string
    {
        $url = Storage::disk($this->disk)->url($path);
        $appUrl = rtrim((string) config('app.url'), '/');

        return $appUrl !== '' && str_starts_with($url, $appUrl.'/') ? substr($url, strlen($appUrl)) : $url;
    }
}
