<?php

namespace App\Models;

use App\Cms\Blocks\BlockTreeRepository;
use App\Cms\Content\ContentType;
use App\Cms\Content\ContentTypeRegistry;
use App\Enums\ContentStatus;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\HasTerms;
use App\Models\Contracts\Revisionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Base for directly published content modules (news, events, projects…; CMS-ARCHITECTURE.md
 * §3, §4.2 "Direct"): the row is live once published. Shared behaviour: workflow status,
 * scheduling, revisions of every save, SEO, terms, builder content and a sidebar choice.
 * What differs per module is described by its ContentType.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string|null $body
 * @property int|null $featured_media_id
 * @property bool $featured
 * @property string $sidebar_mode
 * @property int|null $sidebar_global_block_id
 * @property ContentStatus $status
 * @property Carbon|null $published_at
 * @property Carbon|null $publish_at
 * @property int|null $author_id
 * @property int $lock_version
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
abstract class ContentItem extends Model implements Revisionable
{
    use HasRevisions, HasSeo, HasTerms, SoftDeletes;

    /** Columns every module edits in its form, besides its own (ContentType::fields()). */
    public const COMMON_FIELDS = ['title', 'slug', 'excerpt', 'body', 'featured_media_id', 'featured', 'sidebar_mode', 'sidebar_global_block_id'];

    public const SIDEBAR_MODES = ['default' => 'Use the default sidebar', 'none' => 'No sidebar', 'custom' => 'Choose a sidebar'];

    /** Registry key, e.g. "news". */
    abstract public static function typeKey(): string;

    /**
     * Registry key of this item's type. The model's own key for built-in modules; items of
     * admin-made types share one model and take it from their type.
     */
    public function contentTypeKey(): string
    {
        return static::typeKey();
    }

    public function type(): ContentType
    {
        return app(ContentTypeRegistry::class)->get($this->contentTypeKey());
    }

    public function contentType(): string
    {
        return $this->type()->permissionKey();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'featured' => 'boolean',
            'published_at' => 'datetime',
            'publish_at' => 'datetime',
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
     * Documents listed on the item (modules with ContentType::documents()).
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('position');
    }

    /**
     * Ids chosen in a relation field, in order.
     *
     * @return list<int>
     */
    public function relatedIds(string $field): array
    {
        return ContentRelation::query()
            ->where('owner_type', $this->getMorphClass())
            ->where('owner_id', $this->getKey())
            ->where('field', $field)
            ->orderBy('position')
            ->pluck('related_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Items chosen in a relation field, in order (only those that still exist).
     *
     * @return list<ContentItem>
     */
    public function related(string $field, bool $publishedOnly = true): array
    {
        $definition = $this->type()->relationFields()[$field] ?? null;
        if ($definition === null) {
            return [];
        }

        $ids = $this->relatedIds($field);
        $target = app(ContentTypeRegistry::class)->get((string) $definition['target']);
        $items = $target->query()->whereKey($ids)->with('featuredMedia')
            ->when($publishedOnly, fn ($query) => $query->published())
            ->get()->keyBy('id');
        $ordered = array_values(array_filter(array_map(fn (int $id) => $items[$id] ?? null, $ids)));

        // Partners and team members keep their own display order.
        if ($target->positioned()) {
            usort($ordered, fn (ContentItem $a, ContentItem $b) => [(int) $a->getAttribute('position'), $a->title] <=> [(int) $b->getAttribute('position'), $b->title]);
        }

        return $ordered;
    }

    /**
     * Replace the ids of a relation field (order kept; unknown ids are dropped by the caller).
     *
     * @param  list<int>  $ids
     */
    public function syncRelated(string $field, string $relatedType, array $ids): void
    {
        ContentRelation::query()->where('owner_type', $this->getMorphClass())->where('owner_id', $this->getKey())->where('field', $field)->delete();
        foreach (array_values(array_unique($ids)) as $position => $id) {
            ContentRelation::query()->create([
                'owner_type' => $this->getMorphClass(), 'owner_id' => $this->getKey(), 'field' => $field,
                'related_type' => $relatedType, 'related_id' => $id, 'position' => $position,
            ]);
        }
    }

    /**
     * @return BelongsTo<GlobalBlock, $this>
     */
    public function sidebarBlock(): BelongsTo
    {
        return $this->belongsTo(GlobalBlock::class, 'sidebar_global_block_id');
    }

    /**
     * @param  Builder<static>  $query
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
        return '/'.$this->type()->routePrefix().'/'.$this->slug;
    }

    /**
     * Normalised item for collection blocks and archives (CMS-ARCHITECTURE.md §6.4).
     *
     * @return array<string, mixed>
     */
    public function toItem(): array
    {
        return $this->type()->toItem($this);
    }

    public function toSnapshot(): array
    {
        $fields = $this->only([...self::COMMON_FIELDS, ...array_keys($this->type()->columnFields())]);

        return [
            'schema_version' => '1.0',
            'type' => $this->contentTypeKey(),
            'fields' => $fields,
            'terms' => $this->exists ? $this->terms()->pluck('terms.id')->all() : [],
            'seo' => $this->seoSnapshot(),
            'blocks' => $this->exists ? app(BlockTreeRepository::class)->load($this) : [],
            'documents' => $this->exists && $this->type()->documents()
                ? $this->attachments()->get(['media_id', 'label'])->map(fn (Attachment $a) => ['media_id' => $a->media_id, 'label' => $a->label])->all()
                : [],
            'relations' => $this->exists ? array_map(fn (string $field) => $this->relatedIds($field), array_combine(array_keys($this->type()->relationFields()), array_keys($this->type()->relationFields()))) : [],
            'gallery_items' => $this instanceof Gallery && $this->exists
                ? $this->items()->get(['media_id', 'video_url', 'caption', 'alt_override', 'credit'])
                    ->map(fn (GalleryItem $row) => $row->only(['media_id', 'video_url', 'caption', 'alt_override', 'credit']))->all()
                : [],
        ];
    }

    /**
     * Restores fields, terms and SEO; the block tree is restored (re-validated) by the
     * content service, which knows the acting user.
     */
    public function applySnapshot(array $snapshot): void
    {
        $allowed = [...self::COMMON_FIELDS, ...array_keys($this->type()->columnFields())];
        $fields = array_intersect_key((array) ($snapshot['fields'] ?? []), array_flip($allowed));
        unset($fields['slug']); // never move a live URL by restoring

        // Media deleted since the revision was taken are dropped instead of breaking the item.
        $mediaFields = ['featured_media_id', ...array_keys(array_filter($this->type()->fields(), fn (array $field) => $field['type'] === 'media'))];
        foreach ($mediaFields as $field) {
            if (isset($fields[$field]) && ! Media::query()->whereKey($fields[$field])->exists()) {
                $fields[$field] = null;
            }
        }

        $this->forceFill($fields);
        $this->saveSeo($snapshot['seo'] ?? null);

        if (isset($snapshot['terms']) && $this->type()->taxonomy() !== null) {
            $this->syncTerms((string) $this->type()->taxonomy(), array_map('intval', (array) $snapshot['terms']));
        }
    }
}
