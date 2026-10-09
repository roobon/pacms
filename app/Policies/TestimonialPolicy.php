<?php

namespace App\Policies;

use App\Enums\TestimonialStatus;
use App\Models\Testimonial;
use App\Models\User;

/**
 * Testimonials use their own permissions (SECURITY-ARCHITECTURE.md §3):
 * view, moderate (review, approve, reject, edit), publish and delete.
 */
class TestimonialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('testimonials.view');
    }

    public function view(User $user, Testimonial $testimonial): bool
    {
        return $user->can('testimonials.view');
    }

    public function create(User $user): bool
    {
        return $user->can('testimonials.moderate');
    }

    public function update(User $user, Testimonial $testimonial): bool
    {
        if (! $user->can('testimonials.moderate')) {
            return false;
        }

        return $testimonial->status !== TestimonialStatus::Published || $user->can('testimonials.publish');
    }

    public function delete(User $user, Testimonial $testimonial): bool
    {
        return $user->can('testimonials.delete');
    }
}
