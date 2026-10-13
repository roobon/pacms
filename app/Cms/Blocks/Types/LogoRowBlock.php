<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

/**
 * Logo row (Phase 11I): logos you choose, each with a name and an optional link, e.g. your
 * programmes or members. Unlike the Partners block, it does not read partner records.
 */
class LogoRowBlock extends BlockType
{
    public function slug(): string
    {
        return 'logos';
    }

    public function label(): string
    {
        return 'Logo row';
    }

    public function description(): string
    {
        return 'A row of logos with links, for your programmes, members or campaigns.';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'bi-grid-1x2';
    }

    public function fields(): array
    {
        return [
            Field::text('heading')->label('Heading (optional)'),
            Field::repeater('items', [
                Field::image('image')->label('Logo')->help('Without a logo, the name is shown instead.'),
                Field::text('label')->label('Name')->required()->max(120)->help('Read out by screen readers, and shown under the logo if you choose.'),
                Field::link('link')->label('Link (optional)'),
            ])->label('Logos')->items(1, 24),
            Field::select('size', ['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'])->label('Logo size')->default('lg'),
            Field::checkbox('show_labels')->label('Show the names under the logos')->default(false),
            Field::checkbox('grayscale')->label('Grey until pointed at')->default(false),
        ];
    }

    public function defaults(): array
    {
        return ['content' => ['heading' => null, 'size' => 'lg', 'show_labels' => false, 'grayscale' => false, 'items' => [
            ['label' => 'First programme'], ['label' => 'Second programme'], ['label' => 'Third programme'],
        ]]];
    }
}
