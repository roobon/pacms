<?php

namespace App\Cms\Blocks;

use App\Cms\Fields\Field;

/**
 * A custom block type created in the admin (CMS-ARCHITECTURE.md §11), built from its
 * published revision. Instances are leaf blocks that store only field values; the
 * structure (a block tree with bindings) is expanded on the server when rendering.
 */
final class CustomBlockType extends BlockType
{
    /**
     * @param  list<array<string, mixed>>  $fieldDefinitions  validated (FieldDefinitionValidator)
     * @param  list<array<string, mixed>>  $structure  validated block tree with bindings
     */
    public function __construct(
        public readonly int $id,
        private readonly string $slug,
        private readonly string $name,
        private readonly string $icon,
        private readonly string $about,
        private readonly array $fieldDefinitions,
        public readonly array $structure,
        public readonly int $version,
        public readonly bool $insertable,
    ) {}

    public function slug(): string
    {
        return $this->slug;
    }

    public function label(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->about;
    }

    public function category(): string
    {
        return 'custom';
    }

    public function icon(): string
    {
        return $this->icon;
    }

    public function fields(): array
    {
        return array_map(fn (array $definition) => Field::fromArray($definition), $this->fieldDefinitions);
    }

    /** Custom blocks are not available inside other custom blocks' structures (no cycles). */
    public function contexts(): array
    {
        return ['page', 'global', 'template'];
    }

    public function toArray(): array
    {
        return parent::toArray() + ['custom' => true, 'version' => $this->version, 'insertable' => $this->insertable];
    }
}
