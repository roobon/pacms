<?php

namespace App\Models;

use App\Enums\TestimonialStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One moderation step of a testimonial (CMS-ARCHITECTURE.md §18.1): from, to, who and an
 * internal note (for rejections, the reason). Internal only.
 *
 * @property int $id
 * @property int $testimonial_id
 * @property TestimonialStatus|null $from_status
 * @property TestimonialStatus $to_status
 * @property int|null $actor_id
 * @property string|null $note
 * @property Carbon|null $created_at
 */
class TestimonialModerationLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['testimonial_id', 'from_status', 'to_status', 'actor_id', 'note'];

    protected function casts(): array
    {
        return [
            'from_status' => TestimonialStatus::class,
            'to_status' => TestimonialStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
