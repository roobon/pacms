<?php

namespace App\Models;

use App\Enums\TestimonialStatus;
use App\Models\Concerns\HasRevisions;
use App\Models\Contracts\Revisionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A testimonial (CMS-ARCHITECTURE.md §18): submitted by a registered user or entered by
 * staff, moderated, then published. Not a content module: no page of its own, its own
 * moderation steps (TestimonialModerationService), shown through the Testimonials block.
 *
 * Private (never in a public response): user_id, submitted_ip, consent data and the
 * rejection reason. Public output goes through toItem(), an explicit allowlist.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $designation
 * @property string|null $organization
 * @property int|null $photo_media_id
 * @property string $body
 * @property int|null $rating
 * @property string|null $citation
 * @property Carbon|null $testimonial_date
 * @property int|null $program_id
 * @property int|null $project_id
 * @property int|null $event_id
 * @property string|null $website_url
 * @property TestimonialStatus $status
 * @property string|null $rejection_reason
 * @property Carbon|null $consent_given_at
 * @property string|null $consent_version
 * @property string|null $submitted_ip
 * @property bool $featured
 * @property int $position
 * @property Carbon|null $published_at
 * @property int|null $reviewed_by
 * @property int $lock_version
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Testimonial extends Model implements Revisionable
{
    use HasRevisions, SoftDeletes;

    /** Fields staff edit (and revisions keep). */
    public const EDITABLE = ['name', 'designation', 'organization', 'photo_media_id', 'body', 'rating', 'citation', 'testimonial_date', 'program_id', 'project_id', 'event_id', 'website_url', 'featured', 'position'];

    protected $fillable = self::EDITABLE;

    /** Defence in depth: private columns never appear when a model is serialised. */
    protected $hidden = ['user_id', 'submitted_ip', 'consent_given_at', 'consent_version', 'rejection_reason', 'reviewed_by', 'created_by', 'updated_by'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'draft', 'featured' => false, 'position' => 0, 'lock_version' => 0];

    protected function casts(): array
    {
        return [
            'status' => TestimonialStatus::class,
            'featured' => 'boolean',
            'rating' => 'integer',
            'position' => 'integer',
            'testimonial_date' => 'date',
            'consent_given_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function contentType(): string
    {
        return 'testimonials';
    }

    /**
     * The submitter's IP address (stored packed, 4 or 16 bytes).
     */
    public function setSubmittedIp(?string $ip): void
    {
        $packed = $ip !== null ? @inet_pton($ip) : false;
        $this->forceFill(['submitted_ip' => $packed === false ? null : $packed]);
    }

    public function submittedIp(): ?string
    {
        $packed = $this->getAttribute('submitted_ip');

        return is_string($packed) && $packed !== '' ? (inet_ntop($packed) ?: null) : null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'photo_media_id');
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    /**
     * @return HasMany<TestimonialModerationLog, $this>
     */
    public function moderationLogs(): HasMany
    {
        return $this->hasMany(TestimonialModerationLog::class)->orderByDesc('id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', TestimonialStatus::Published)->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === TestimonialStatus::Published && $this->published_at !== null && ! $this->published_at->isFuture() && ! $this->trashed();
    }

    /**
     * Testimonial text is plain text: tags removed, line breaks kept, spaces tidied.
     */
    public static function plainText(string $text): string
    {
        $text = strip_tags(str_replace(["\r\n", "\r"], "\n", $text));
        $text = (string) preg_replace("/[ \t]+/u", ' ', $text);

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /** Short label for lists and logs. */
    public function label(): string
    {
        return trim($this->name.($this->organization ? ', '.$this->organization : ''));
    }

    /**
     * Public item (CMS-ARCHITECTURE.md §6.4) for blocks and the API. An explicit allowlist:
     * nothing about the submitter's account, address, consent or moderation is included.
     *
     * @return array<string, mixed>
     */
    public function toItem(): array
    {
        $photo = $this->photo !== null && $this->photo->isPublic() ? $this->photo->toImageArray('(min-width: 768px) 96px, 72px') : null;

        return [
            'key' => 'testimonials:'.$this->id,
            'kind' => 'testimonials',
            'title' => $this->name,
            'url' => null,
            'external' => false,
            'excerpt' => null,
            'image' => $photo,
            'date' => $this->testimonial_date?->toDateString(),
            'meta' => array_filter([
                'quote' => $this->body,
                'designation' => $this->designation,
                'organization' => $this->organization,
                'rating' => $this->rating,
                'citation' => $this->citation,
                'website' => $this->website_url,
            ], fn ($value) => $value !== null && $value !== ''),
        ];
    }

    public function toSnapshot(): array
    {
        $fields = $this->only(self::EDITABLE);
        $fields['testimonial_date'] = $this->testimonial_date?->toDateString();

        return ['schema_version' => '1.0', 'type' => 'testimonials', 'fields' => $fields];
    }

    public function applySnapshot(array $snapshot): void
    {
        $fields = array_intersect_key((array) ($snapshot['fields'] ?? []), array_flip(self::EDITABLE));
        if (isset($fields['photo_media_id']) && ! Media::query()->whereKey($fields['photo_media_id'])->exists()) {
            $fields['photo_media_id'] = null;
        }
        $this->forceFill($fields);
    }
}
