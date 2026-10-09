<?php

namespace App\Cms\Blocks;

use App\Cms\Fields\Field;

/**
 * Definition of a core block type (CMS-ARCHITECTURE.md §5.2). Core types are PHP classes
 * synchronised into `block_types` by `php artisan pacms:blocks:sync`; custom block types
 * (Phase 5) use the same definition shape stored in the database.
 */
abstract class BlockType
{
    /** Unique slug referenced by stored blocks and JSON (`type`). */
    abstract public function slug(): string;

    abstract public function label(): string;

    /** basic | layout | content | media | organization | dynamic | external | structural */
    abstract public function category(): string;

    abstract public function icon(): string;

    /**
     * @return list<Field>
     */
    public function fields(): array
    {
        return [];
    }

    public function description(): string
    {
        return '';
    }

    /**
     * Allowed content sources: static, dynamic, external.
     *
     * @return list<string>
     */
    public function sourceModes(): array
    {
        return ['static'];
    }

    /**
     * External providers whose sources this block can show (e.g. ["feed"]), when it supports
     * external mode.
     *
     * @return list<string>
     */
    public function externalProviders(): array
    {
        return [];
    }

    /** Dynamic entity for this block (e.g. "news"), when it supports dynamic mode. */
    public function dynamicEntity(): ?string
    {
        return null;
    }

    /**
     * Display modes this block supports (DisplayModeRegistry keys).
     *
     * @return list<string>
     */
    public function displayModes(): array
    {
        return [];
    }

    /**
     * Allowed child types; null = no children, ['*'] = any type allowed under this one.
     *
     * @return list<string>|null
     */
    public function allowedChildren(): ?array
    {
        return null;
    }

    /**
     * Required parent types; null = may be placed anywhere (including page root),
     * [] = page level only.
     *
     * @return list<string>|null
     */
    public function allowedParents(): ?array
    {
        return null;
    }

    /**
     * Types this block may never contain, even when allowedChildren is ['*'].
     *
     * @return list<string>
     */
    public function excludedChildren(): array
    {
        return [];
    }

    public function maxChildren(): int
    {
        return 50;
    }

    /**
     * Where the block may be used: "page" (pages and other content), "global" (global
     * blocks), "template" and "structure" (custom block type structures). null = everywhere.
     *
     * @return list<string>|null
     */
    public function contexts(): ?array
    {
        return null;
    }

    /**
     * Structural wrappers (repeat, when) that disappear when rendered: placement rules
     * look through them to the nearest real parent.
     */
    public function isTransparent(): bool
    {
        return false;
    }

    final public function allowedIn(string $context): bool
    {
        return $this->contexts() === null || in_array($context, $this->contexts(), true);
    }

    /**
     * Public data the block shows besides its own fields, added to the payload as `data`
     * (e.g. a Menu block's resolved menu, the site logo). null = none.
     *
     * @param  array<string, mixed>  $content  the block's validated content
     * @return array<string, mixed>|null
     */
    public function data(array $content): ?array
    {
        return null;
    }

    /**
     * Default values for a newly inserted block (content/layout/style/display/children).
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [];
    }

    final public function acceptsChildren(): bool
    {
        return $this->allowedChildren() !== null;
    }

    final public function allowsChild(BlockType $child): bool
    {
        $allowed = $this->allowedChildren();

        if ($allowed === null || in_array($child->slug(), $this->excludedChildren(), true)) {
            return false;
        }

        return in_array('*', $allowed, true) || in_array($child->slug(), $allowed, true);
    }

    final public function allowedUnder(?BlockType $parent): bool
    {
        $parents = $this->allowedParents();

        // [] = page level only.
        if ($parents === []) {
            return $parent === null;
        }

        if ($parents !== null && ($parent === null || ! in_array($parent->slug(), $parents, true))) {
            return false;
        }

        return $parent === null || $parent->allowsChild($this);
    }

    /**
     * Serialised definition for the builder and the block_types table.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug(),
            'label' => $this->label(),
            'description' => $this->description(),
            'category' => $this->category(),
            'icon' => $this->icon(),
            'fields' => array_map(fn (Field $field) => $field->toArray(), $this->fields()),
            'capabilities' => [
                'source_modes' => $this->sourceModes(),
                'dynamic_entity' => $this->dynamicEntity(),
                'external_providers' => $this->externalProviders(),
                'display_modes' => $this->displayModes(),
                'allowed_children' => $this->allowedChildren(),
                'excluded_children' => $this->excludedChildren(),
                'allowed_parents' => $this->allowedParents(),
                'max_children' => $this->maxChildren(),
                'contexts' => $this->contexts(),
                'transparent' => $this->isTransparent(),
            ],
            'defaults' => $this->defaults(),
        ];
    }
}
