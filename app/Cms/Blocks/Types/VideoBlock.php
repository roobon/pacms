<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

class VideoBlock extends BlockType
{
    public function slug(): string
    {
        return 'video';
    }

    public function label(): string
    {
        return 'Video';
    }

    public function category(): string
    {
        return 'basic';
    }

    public function icon(): string
    {
        return 'bi-play-btn';
    }

    public function description(): string
    {
        return 'YouTube or Vimeo. Nothing loads from the video site until the visitor clicks play.';
    }

    public function fields(): array
    {
        return [
            Field::videoUrl('url')->label('YouTube or Vimeo link')->required(),
            Field::text('title')->label('Video title')->required()->help('Read by screen readers.'),
            Field::image('poster')->label('Cover image (optional)'),
        ];
    }
}
