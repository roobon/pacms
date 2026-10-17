<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

class ImageBlock extends BlockType
{
    public const SIZES = ['natural' => 'Natural size', 'fill' => 'Fill the width'];

    public const HEIGHTS = ['auto' => 'Use the crop above', 'sm' => 'Low (40% of the screen)', 'md' => 'Medium (60% of the screen)', 'lg' => 'Tall (80% of the screen)'];

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
            Field::select('shape', ['square' => 'Square corners', 'rounded' => 'Rounded corners', 'circle' => 'Circle (cropped square)'])->label('Shape')->default('square'),
            // Phase 11W: a banner photo. In a section set to Full width it runs edge to edge.
            Field::select('size', self::SIZES)->label('Size')->default('natural')
                ->help('“Fill the width” runs edge to edge in a section whose Layout → Width is Full.'),
            Field::select('height', self::HEIGHTS)->label('Banner height (when filling the width)')->default('auto'),
        ];
    }
}
