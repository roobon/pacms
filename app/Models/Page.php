<?php

namespace App\Models;

use App\Cms\Blocks\BlockTreeRepository;
use App\Enums\ContentStatus;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\HasSeo;
use App\Models\Contracts\Revisionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A CMS page with staged publishing (CMS-ARCHITECTURE.md §4.2):
 * the row is the working copy; visitors see the published snapshot
 * (published_revision_id) at published_path.
 *
 * @property ContentStatus $status
 * @property Carbon|null $publish_at
 * @property Carbon|null $published_at
 * @property Carbon|null $first_published_at
 */
class Page extends Model implements Revisionable
{
    use HasRevisions, HasSeo, SoftDeletes;

    public const SNAPSHOT_FIELDS = ['title', 'slug', 'parent_id', 'excerpt', 'featured_media_id', 'template'];

    /**
     * Editable working-copy fields. Workflow, path and publishing columns are set by services.
     *
     * @var list<string>
     */
    protected $fillable = ['title', 'slug', 'parent_id', 'excerpt', 'featured_media_id', 'template'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'template' => 'default',
        'has_unpublished_changes' => true,
        'lock_version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'has_unpublished_changes' => 'boolean',
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
            'first_published_at' => 'datetime',
        ];
    }

    public function contentType(): string
    {
        return 'pages';
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    /**
     * @return HasMany<Page, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id');
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
     * @return BelongsTo<Revision, $this>
     */
    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(Revision::class, 'published_revision_id');
    }

    /**
     * Pages visitors can see: a published snapshot exists and the page is not archived.
     *
     * @param  Builder<Page>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereNotNull('published_revision_id')
            ->whereNotNull('published_path')
            ->where('status', '!=', ContentStatus::Archived);
    }

    public function isLive(): bool
    {
        return $this->published_revision_id !== null
            && $this->published_path !== null
            && $this->status !== ContentStatus::Archived
            && ! $this->trashed();
    }

    public function publicUrl(): ?string
    {
        return $this->isLive() ? '/'.$this->published_path : null;
    }

    public function depth(): int
    {
        return substr_count((string) $this->path, '/');
    }

    public function toSnapshot(): array
    {
        return [
            'schema_version' => '1.0',
            'type' => 'page',
            'fields' => $this->only(self::SNAPSHOT_FIELDS) + ['path' => $this->path],
            'seo' => $this->seoSnapshot(),
            'blocks' => $this->exists ? app(BlockTreeRepository::class)->load($this) : [],
        ];
    }

    /**
     * Restores fields and SEO. The block tree is restored (and re-validated) by
     * PageService::restore(), which knows the acting user.
     */
    public function applySnapshot(array $snapshot): void
    {
        $fields = array_intersect_key((array) ($snapshot['fields'] ?? []), array_flip(self::SNAPSHOT_FIELDS));

        // Never re-attach to a parent or image that no longer exists.
        if (isset($fields['parent_id']) && ! Page::query()->whereKey($fields['parent_id'])->exists()) {
            unset($fields['parent_id']);
        }
        if (isset($fields['featured_media_id']) && ! Media::query()->whereKey($fields['featured_media_id'])->exists()) {
            $fields['featured_media_id'] = null;
        }

        $this->fill($fields);
        $this->saveSeo($snapshot['seo'] ?? null);
    }
}
