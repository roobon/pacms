<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

/**
 * A collection of published items of one content module (events, projects…), shown as
 * grid, list, carousel or featured. The filters and orders come from the module's
 * ContentType (SourceRegistry); subclasses only name the module and their defaults.
 */
abstract class ContentCollectionBlock extends BlockType
{
    /** Registry key of the listed module, e.g. "projects". */
    abstract protected function entity(): string;

    /** Text shown when nothing matches. */
    abstract protected function emptyText(): string;

    /**
     * Source defaults besides mode, provider and entity (order, limit, filters).
     *
     * @return array<string, mixed>
     */
    protected function sourceDefaults(): array
    {
        return ['order' => 'latest', 'limit' => 6];
    }

    public function slug(): string
    {
        return $this->entity();
    }

    public function category(): string
    {
        return 'dynamic';
    }

    public function fields(): array
    {
        return [
            Field::text('heading')->label('Heading (optional)'),
            Field::text('empty_text')->label('Text when there is nothing to show')->default($this->emptyText()),
            Field::checkbox('show_excerpt')->label('Show summary')->default(true),
            Field::checkbox('show_date')->label('Show date')->default(true),
        ];
    }

    public function sourceModes(): array
    {
        return ['dynamic'];
    }

    public function dynamicEntity(): ?string
    {
        return $this->entity();
    }

    public function displayModes(): array
    {
        return ['grid', 'list', 'carousel', 'featured'];
    }

    public function defaults(): array
    {
        return [
            'content' => ['heading' => $this->label(), 'empty_text' => $this->emptyText(), 'show_excerpt' => true, 'show_date' => true],
            'source' => ['mode' => 'dynamic', 'provider' => 'cms', 'entity' => $this->entity()] + $this->sourceDefaults(),
            'display' => ['mode' => 'grid', 'columns' => ['desktop' => 3, 'tablet' => 2, 'mobile' => 1], 'card_style' => 'elevated', 'image_ratio' => '16:9'],
        ];
    }
}
