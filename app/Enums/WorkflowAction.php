<?php

namespace App\Enums;

enum WorkflowAction: string
{
    case Submit = 'submit';
    case Approve = 'approve';
    case RequestChanges = 'request_changes';
    case Publish = 'publish';
    case Schedule = 'schedule';
    case Unschedule = 'unschedule';
    case Unpublish = 'unpublish';
    case Archive = 'archive';
    case Restore = 'restore';

    public function label(): string
    {
        return match ($this) {
            self::Submit => 'Submit for review',
            self::Approve => 'Approve',
            self::RequestChanges => 'Request changes',
            self::Publish => 'Publish',
            self::Schedule => 'Schedule',
            self::Unschedule => 'Cancel schedule',
            self::Unpublish => 'Unpublish',
            self::Archive => 'Archive',
            self::Restore => 'Restore from archive',
        };
    }

    /**
     * Permission suffix required for this action ("{type}.{suffix}").
     */
    public function permission(): string
    {
        return match ($this) {
            self::Submit => 'submit',
            self::Approve, self::RequestChanges => 'approve',
            default => 'publish',
        };
    }
}
