<?php

namespace App\Enums;

/**
 * Editorial status of an item's working copy (CMS-ARCHITECTURE.md §4.1).
 *
 * For staged content (pages) the status describes the working copy; whether the item is
 * live is determined separately by its published snapshot. A live page that is edited
 * goes back to Draft while the previously published version stays online.
 */
enum ContentStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InReview => 'In review',
            self::Approved => 'Approved',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }
}
