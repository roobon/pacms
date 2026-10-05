<?php

namespace App\Enums;

enum MediaKind: string
{
    case Image = 'image';
    case Document = 'document';
    case Video = 'video';
    case Audio = 'audio';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Image => 'bi-image',
            self::Document => 'bi-file-earmark-text',
            self::Video => 'bi-film',
            self::Audio => 'bi-music-note-beamed',
        };
    }
}
