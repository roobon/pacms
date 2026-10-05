<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

class SectionBlock extends BlockType
{
    public function slug(): string
    {
        return 'section';
    }

    public function label(): string
    {
        return 'Section';
    }

    public function category(): string
    {
        return 'layout';
    }

    public function icon(): string
    {
        return 'bi-square';
    }

    public function description(): string
    {
        return 'A full-width band of the page. Its content is kept within the site width unless the layout says otherwise.';
    }

    public function fields(): array
    {
        return [Field::text('aria_label')->label('Section name for screen readers (optional)')->max(120)];
    }

    public function allowedParents(): ?array
    {
        return [];
    }

    public function allowedChildren(): ?array
    {
        return ['*'];
    }

    /** @return list<string> */
    public function excludedChildren(): array
    {
        return ['section', 'hero'];
    }

    public function defaults(): array
    {
        return [
            'layout' => ['container' => 'boxed', 'padding' => ['top' => ['$token' => 'space.section'], 'bottom' => ['$token' => 'space.section']]],
        ];
    }
}
