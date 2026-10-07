<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Models\Concerns\HasTerms;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Minimal News (Phase 4) — enough for dynamic blocks and a detail page.
 * Direct publishing (CMS-ARCHITECTURE.md §4.2): the row is live once published.
 *
 * @property ContentStatus $status
 * @property Carbon|null $published_at
 */
class News extends Model
{
    use HasTerms, SoftDeletes;

    protected $table = 'news';

    protected $fillable = ['title', 'slug', 'excerpt', 'featured_media_id', 'featured'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'draft', 'featured' => false];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_media_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id')->withTrashed();
    }

    /**
     * @param  Builder<News>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ContentStatus::Published)->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published && $this->published_at !== null && ! $this->published_at->isFuture() && ! $this->trashed();
    }

    public function url(): string
    {
        return '/news/'.$this->slug;
    }

    /**
     * Normalised item for collection blocks (CMS-ARCHITECTURE.md §6.4).
     *
     * @return array<string, mixed>
     */
    public function toItem(): array
    {
        $category = $this->relationLoaded('terms') ? $this->terms->firstWhere('taxonomy', 'news_category') : null;

        return [
            'key' => 'news:'.$this->id,
            'kind' => 'news',
            'title' => $this->title,
            'url' => $this->url(),
            'external' => false,
            'excerpt' => HtmlSanitizer::toText($this->excerpt),
            'image' => $this->featuredMedia?->toImageArray('(min-width: 992px) 33vw, 100vw'),
            'date' => $this->published_at?->toIso8601String(),
            'meta' => array_filter(['category' => $category?->name]),
        ];
    }
}
