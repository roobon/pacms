<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Fields\Field;

/**
 * One gallery's photos and videos as a grid with a lightbox. The gallery is chosen in the
 * source ("One gallery"); without a choice, the newest gallery is shown.
 */
class GalleryBlock extends ContentCollectionBlock
{
    protected function entity(): string
    {
        return 'galleries';
    }

    protected function emptyText(): string
    {
        return 'Choose a gallery.';
    }

    protected function sourceDefaults(): array
    {
        return ['order' => 'latest', 'limit' => 1];
    }

    public function slug(): string
    {
        return 'gallery';
    }

    public function label(): string
    {
        return 'Gallery';
    }

    public function description(): string
    {
        return 'Photos and videos of one gallery, with a full-screen viewer.';
    }

    public function icon(): string
    {
        return 'bi-grid-3x3-gap';
    }

    public function displayModes(): array
    {
        return ['grid'];
    }

    public function fields(): array
    {
        return [
            Field::text('heading')->label('Heading (optional)'),
            Field::select('columns', ['2' => '2', '3' => '3', '4' => '4', '5' => '5'])->label('Columns (desktop)')->default('4'),
            Field::checkbox('show_captions')->label('Show captions under the photos')->default(false),
            Field::checkbox('show_link')->label('Link to the gallery page')->default(true),
        ];
    }

    public function defaults(): array
    {
        $defaults = parent::defaults();
        $defaults['content'] = ['heading' => null, 'columns' => '4', 'show_captions' => false, 'show_link' => true];
        $defaults['display'] = ['mode' => 'grid'];

        return $defaults;
    }
}
