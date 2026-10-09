<?php

namespace App\Http\Resources\Public;

use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user's own submission, for their account (API-ARCHITECTURE.md §4): what they sent and
 * where it stands. No internal notes; the rejection reason only if the site allows it.
 *
 * @mixin Testimonial
 */
class OwnTestimonialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'organization' => $this->organization,
            'designation' => $this->designation,
            'quote' => $this->body,
            'status' => $this->status->value,
            'status_label' => $this->status->publicLabel(),
            'submitted_at' => $this->created_at?->toIso8601String(),
            'rejection_reason' => $this->when(
                (bool) config('pacms.testimonials.show_rejection_reason') && $this->rejection_reason !== null,
                fn () => $this->rejection_reason,
            ),
        ];
    }
}
