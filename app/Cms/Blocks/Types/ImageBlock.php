<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

class ImageBlock extends BlockType
{
    public function slug(): string
    {
        return 'image';
    }

    public function label(): string
    {
        return 'Image';
    }

    public function category(): string
    {
        return 'basic';
    }

    public function icon(): string
    {
        return 'bi-image';
    }

    public function fields(): array
    {
        return [
            Field::image('image')->label('Image')->required(),
            Field::text('alt')->label('Alternative text')->help('Leave empty to use the alt text from the media library.'),
            Field::textarea('caption')->label('Caption')->max(500),
            Field::link('link')->label('Link (optional)'),
            Field::select('ratio', ['auto' => 'Original', '1:1' => 'Square', '4:3' => '4:3', '16:9' => '16:9', '21:9' => 'Wide'])->label('Crop')->default('auto'),
        ];
    }
}
