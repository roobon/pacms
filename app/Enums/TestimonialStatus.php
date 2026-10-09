<?php

namespace App\Enums;

/**
 * Moderation status of a testimonial (CMS-ARCHITECTURE.md §18.1). Approved means accepted
 * but not shown yet; only Published is visible on the website.
 */
enum TestimonialStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Published = 'published';
    case Rejected = 'rejected';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending',
            self::UnderReview => 'Under review',
            self::Approved => 'Approved',
            self::Published => 'Published',
            self::Rejected => 'Rejected',
            self::Archived => 'Archived',
        };
    }

    /** Admin badge variant. */
    public function badge(): string
    {
        return match ($this) {
            self::Pending, self::UnderReview => 'warning',
            self::Approved => 'info',
            self::Published => 'success',
            self::Rejected => 'danger',
            default => 'neutral',
        };
    }

    /** What the submitter sees in their account. */
    public function publicLabel(): string
    {
        return match ($this) {
            self::Draft, self::Pending => 'Waiting for review',
            self::UnderReview => 'Being reviewed',
            self::Approved => 'Accepted',
            self::Published => 'Published',
            self::Rejected => 'Not accepted',
            self::Archived => 'No longer shown',
        };
    }

    /**
     * Waiting for a moderator (the queue badge).
     *
     * @return list<self>
     */
    public static function awaitingModeration(): array
    {
        return [self::Pending, self::UnderReview];
    }
}
