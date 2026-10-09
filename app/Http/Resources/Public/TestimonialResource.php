<?php

namespace App\Http\Resources\Public;

use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A published testimonial (API-ARCHITECTURE.md §3). Explicit allowlist: never the e-mail,
 * user ID, IP address, consent data or rejection reason (CMS-ARCHITECTURE.md §18.2).
 *
 * @mixin Testimonial
 */
class TestimonialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $photo = $this->photo !== null && $this->photo->isPublic() ? $this->photo->toImageArray('96px') : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'designation' => $this->designation,
            'organization' => $this->organization,
            'quote' => $this->body,
            'rating' => $this->rating,
            'citation' => $this->citation,
            'date' => $this->testimonial_date?->toDateString(),
            'website' => $this->website_url,
            'photo' => $photo,
            'featured' => $this->featured,
        ];
    }
}
