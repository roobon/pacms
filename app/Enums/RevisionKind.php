<?php

namespace App\Enums;

enum RevisionKind: string
{
    case Manual = 'manual';
    case Published = 'published';
    case Autosave = 'autosave';
    case Restore = 'restore';
    case Import = 'import';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Saved',
            self::Published => 'Published',
            self::Autosave => 'Autosave',
            self::Restore => 'Restored',
            self::Import => 'Imported',
        };
    }
}
