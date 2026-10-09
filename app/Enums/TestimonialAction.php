<?php

namespace App\Enums;

/**
 * Moderation steps (CMS-ARCHITECTURE.md §18.1):
 *
 *   Draft ─submit─▶ Pending ─start review─▶ Under review ─approve─▶ Approved ─publish─▶ Published ─archive─▶ Archived
 *                     │                         │                                       └─unpublish─▶ Approved
 *                     └────────── reject (internal reason) ──▶ Rejected ─reopen─▶ Under review
 *
 * Testimonials entered in the admin start as drafts and may be approved straight away.
 */
enum TestimonialAction: string
{
    case Submit = 'submit';
    case StartReview = 'start_review';
    case Approve = 'approve';
    case Reject = 'reject';
    case Publish = 'publish';
    case Unpublish = 'unpublish';
    case Archive = 'archive';
    case Reopen = 'reopen';

    public function label(): string
    {
        return match ($this) {
            self::Submit => 'Send to moderation',
            self::StartReview => 'Start review',
            self::Approve => 'Approve',
            self::Reject => 'Reject',
            self::Publish => 'Publish',
            self::Unpublish => 'Unpublish',
            self::Archive => 'Archive',
            self::Reopen => 'Review again',
        };
    }

    /**
     * Permission needed (testimonials.moderate or testimonials.publish).
     */
    public function permission(): string
    {
        return match ($this) {
            self::Publish, self::Unpublish, self::Archive => 'testimonials.publish',
            default => 'testimonials.moderate',
        };
    }

    /**
     * @return list<TestimonialStatus>
     */
    public function allowedFrom(): array
    {
        return match ($this) {
            self::Submit => [TestimonialStatus::Draft],
            self::StartReview => [TestimonialStatus::Pending],
            self::Approve => [TestimonialStatus::Draft, TestimonialStatus::Pending, TestimonialStatus::UnderReview],
            self::Reject => [TestimonialStatus::Pending, TestimonialStatus::UnderReview],
            self::Publish => [TestimonialStatus::Approved],
            self::Unpublish => [TestimonialStatus::Published],
            self::Archive => [TestimonialStatus::Approved, TestimonialStatus::Published],
            self::Reopen => [TestimonialStatus::Rejected, TestimonialStatus::Archived],
        };
    }

    public function to(): TestimonialStatus
    {
        return match ($this) {
            self::Submit => TestimonialStatus::Pending,
            self::StartReview, self::Reopen => TestimonialStatus::UnderReview,
            self::Approve, self::Unpublish => TestimonialStatus::Approved,
            self::Reject => TestimonialStatus::Rejected,
            self::Publish => TestimonialStatus::Published,
            self::Archive => TestimonialStatus::Archived,
        };
    }
}
