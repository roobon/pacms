<?php

namespace App\Cms\Exchange;

use App\Cms\Blocks\BlockRegistry;
use App\Cms\Blocks\BlockType;
use App\Cms\Design\TokenCatalog;

/**
 * Generates the machine-readable schema (JSON Schema 2020-12) and the "AI prompt helper"
 * from the block registry, so both always match the block types this site offers
 * (CMS-BLOCK-SCHEMA.md; one definition drives builder, validation and schema).
 */
final class SchemaGenerator
{
    public const VERSION = '1.0';

    public function __construct(private readonly BlockRegistry $registry) {}

    /**
     * @param  bool  $includeCustom  include this site's custom block types (false for the repository file)
     * @return array<string, mixed>
     */
    public function document(bool $includeCustom = true): array
    {
        $types = array_values(array_filter($this->types($includeCustom), fn (BlockType $type) => $type->allowedIn('page') || $type->allowedIn('template')));

        $defs = [
            'asset_ref' => ['type' => 'object', 'required' => ['$asset'], 'properties' => ['$asset' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9_-]{1,64}$']], 'additionalProperties' => false],
            'media_ref' => ['type' => 'object', 'required' => ['$media'], 'properties' => ['$media' => ['type' => 'integer', 'minimum' => 1]], 'additionalProperties' => false],
            'image' => ['oneOf' => [['$ref' => '#/$defs/asset_ref'], ['$ref' => '#/$defs/media_ref']]],
            'token' => ['type' => 'object', 'required' => ['$token'], 'properties' => ['$token' => ['type' => 'string', 'enum' => array_keys(TokenCatalog::definitions())]], 'additionalProperties' => false],
            'color' => ['oneOf' => [['$ref' => '#/$defs/token'], ['type' => 'string', 'pattern' => '^#([0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$']]],
            'entity_ref' => [
                'type' => 'object', 'required' => ['$ref'],
                'properties' => ['$ref' => ['type' => 'object', 'required' => ['entity'], 'properties' => [
                    'entity' => ['type' => 'string'], 'slug' => ['type' => 'string'], 'path' => ['type' => 'string'], 'taxonomy' => ['type' => 'string'],
                ]]],
            ],
            'link' => ['oneOf' => [
                ['type' => 'object', 'required' => ['type', 'url'], 'properties' => ['type' => ['const' => 'url'], 'url' => ['type' => 'string', 'maxLength' => 2048, 'pattern' => '^(https?://|/|#|mailto:|tel:)'], 'new_tab' => ['type' => 'boolean']], 'additionalProperties' => false],
                ['type' => 'object', 'required' => ['type', 'ref'], 'properties' => ['type' => ['const' => 'entity'], 'ref' => ['$ref' => '#/$defs/entity_ref'], 'new_tab' => ['type' => 'boolean']], 'additionalProperties' => false],
                ['type' => 'object', 'required' => ['type', 'anchor'], 'properties' => ['type' => ['const' => 'anchor'], 'anchor' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9-]{0,63}$']], 'additionalProperties' => false],
                ['type' => 'object', 'required' => ['type', 'email'], 'properties' => ['type' => ['const' => 'email'], 'email' => ['type' => 'string', 'format' => 'email']], 'additionalProperties' => false],
            ]],
            'asset' => [
                'type' => 'object', 'required' => ['key'],
                'properties' => [
                    'key' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9_-]{1,64}$'],
                    'url' => ['type' => 'string', 'format' => 'uri', 'pattern' => '^https?://'],
                    'media' => ['type' => 'integer'],
                    'alt' => ['type' => 'string', 'maxLength' => 255],
                    'credit' => ['type' => 'string', 'maxLength' => 255],
                    'strategy' => ['enum' => ['download', 'external', 'existing']],
                ],
            ],
            'node' => [
                'type' => 'object',
                'required' => ['type'],
                'properties' => [
                    'type' => ['enum' => array_map(fn (BlockType $type) => $type->slug(), $types)],
                    'key' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9_-]{1,64}$'],
                    'uuid' => ['type' => 'string'],
                    'name' => ['type' => 'string', 'maxLength' => 120],
                    'hidden' => ['type' => 'boolean'],
                    'content' => ['type' => 'object'],
                    'source' => ['type' => 'object', 'properties' => ['mode' => ['enum' => ['static', 'dynamic', 'external']]]],
                    'display' => ['type' => 'object'],
                    'layout' => ['type' => 'object'],
                    'style' => ['type' => 'object'],
                    'responsive' => ['type' => 'object', 'properties' => ['tablet' => ['type' => 'object'], 'mobile' => ['type' => 'object']], 'additionalProperties' => false],
                    'advanced' => ['type' => 'object'],
                    'children' => ['type' => 'array', 'items' => ['$ref' => '#/$defs/node']],
                ],
                'additionalProperties' => false,
                'allOf' => array_map(fn (BlockType $type) => [
                    'if' => ['properties' => ['type' => ['const' => $type->slug()]]],
                    'then' => ['properties' => ['content' => ['$ref' => '#/$defs/content__'.$this->defName($type->slug())]]],
                ], $types),
            ],
        ];

        foreach ($types as $type) {
            $defs['content__'.$this->defName($type->slug())] = $this->content($type);
        }

        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            '$id' => 'urn:pacms:schema:'.self::VERSION.':document',
            'title' => 'PACMS document '.self::VERSION,
            'description' => 'Import/export envelope for Probha Aurora CMS. See CMS-BLOCK-SCHEMA.md.',
            'type' => 'object',
            'required' => ['schema_version', 'kind'],
            'properties' => [
                'schema_version' => ['type' => 'string', 'pattern' => '^1\\.[0-9]+$'],
                'kind' => ['enum' => DocumentReader::KINDS],
                'meta' => ['type' => 'object', 'properties' => ['generator' => ['type' => 'string'], 'created_at' => ['type' => 'string'], 'title' => ['type' => 'string'], 'notes' => ['type' => 'string']]],
                'assets' => ['type' => 'array', 'maxItems' => DocumentReader::MAX_ASSETS, 'items' => ['$ref' => '#/$defs/asset']],
                'blocks' => ['type' => 'array', 'items' => ['$ref' => '#/$defs/node']],
                'template' => ['type' => 'object', 'required' => ['name'], 'properties' => ['name' => ['type' => 'string'], 'description' => ['type' => 'string'], 'scope' => ['enum' => ['block', 'section', 'page']], 'category' => ['type' => 'string']]],
                'page' => [
                    'type' => 'object',
                    'required' => ['title'],
                    'properties' => [
                        'title' => ['type' => 'string', 'maxLength' => 255],
                        'slug' => ['type' => 'string', 'pattern' => '^[a-z0-9]+(-[a-z0-9]+)*$'],
                        'parent' => ['$ref' => '#/$defs/entity_ref'],
                        'excerpt' => ['type' => 'string', 'maxLength' => 1000],
                        'featured_image' => ['$ref' => '#/$defs/image'],
                        'template' => ['enum' => array_keys(config('pacms.pages.templates'))],
                        'seo' => ['type' => 'object'],
                        'blocks' => ['type' => 'array', 'items' => ['$ref' => '#/$defs/node']],
                    ],
                ],
            ],
            '$defs' => $defs,
        ];
    }

    /**
     * Instructions to paste into an AI assistant before asking it for a page.
     */
    public function promptHelper(bool $includeCustom = true): string
    {
        $lines = [
            'You generate content for Probha Aurora CMS (PACMS) as JSON. Follow these rules exactly:',
            '1. Output ONE JSON object only (no explanations). Never output code, <script>, event handlers (onclick…), javascript: URLs or CSS.',
            '2. Start with "schema_version": "1.0" and "kind": "page" (or "section", "block", "template").',
            '3. A page document: {"schema_version":"1.0","kind":"page","meta":{"generator":"<your name>","title":"…"},"assets":[…],"page":{"title":"…","slug":"…","excerpt":"…","seo":{"description":"…"},"blocks":[…]}}',
            '4. Each block: {"type": "<one of the types below>", "key": "short-id", "content": {…}, "layout": {…}, "style": {…}, "children": […]}. Only "type" is required.',
            '5. Images: list them in "assets" as {"key":"hero","url":"https://…","alt":"description","strategy":"download"} and use {"$asset":"hero"} in image fields. Never invent media ids.',
            '6. Links: {"type":"url","url":"https://… or /path"}, {"type":"anchor","anchor":"contact"} or {"type":"entity","ref":{"$ref":{"entity":"pages","path":"about"}}}.',
            '7. Colours and spacing: prefer design tokens such as {"$token":"color.primary"} and {"$token":"space.5"}.',
            '8. Rich text ("rich-text" fields) may only use: p, br, strong, em, a, ul, ol, li, blockquote, h2–h6, table. Everything else is removed.',
            '9. Usually build a page from "section" blocks; put headings, text, buttons, columns, cards inside sections. Use exactly one heading with "level": "1" per page.',
            '10. The import always creates a draft; a person reviews and publishes it.',
            '',
            'Block types available on this site (fields: key:type, * = required; children: allowed child types):',
        ];

        foreach ($this->types($includeCustom) as $type) {
            if (! $type->allowedIn('page') || $type->slug() === 'global-ref') {
                continue;
            }
            $fields = array_map(fn ($f) => $this->describeField($f->toArray()), $type->fields());
            $children = $type->allowedChildren();
            $lines[] = sprintf(
                '- %s (%s): %s%s%s',
                $type->slug(),
                $type->label(),
                $fields === [] ? 'no fields' : implode(', ', $fields),
                $children === null ? '' : '; children: '.(in_array('*', $children, true) ? 'any block'.($type->excludedChildren() ? ' except '.implode(', ', $type->excludedChildren()) : '') : implode(', ', $children)),
                $type->allowedParents() === [] ? '; top level only' : ($type->allowedParents() ? '; only inside '.implode(', ', $type->allowedParents()) : ''),
            );
        }

        $lines[] = '';
        $lines[] = 'Example:';
        $lines[] = (string) json_encode([
            'schema_version' => '1.0',
            'kind' => 'page',
            'meta' => ['generator' => 'AI assistant', 'title' => 'About us'],
            'assets' => [['key' => 'team-photo', 'url' => 'https://images.example.org/team.jpg', 'alt' => 'Our team planting trees', 'strategy' => 'download']],
            'page' => [
                'title' => 'About us', 'slug' => 'about-us', 'excerpt' => 'Who we are and what we do.',
                'blocks' => [[
                    'type' => 'section', 'key' => 'intro',
                    'children' => [
                        ['type' => 'heading', 'content' => ['text' => 'About us', 'level' => '1']],
                        ['type' => 'rich-text', 'content' => ['html' => '<p>We help schools and young people care for the environment.</p>']],
                        ['type' => 'image', 'content' => ['image' => ['$asset' => 'team-photo'], 'alt' => 'Our team planting trees']],
                        ['type' => 'button', 'content' => ['label' => 'Contact us', 'link' => ['type' => 'url', 'url' => '/contact']]],
                    ],
                ]],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(BlockType $type): array
    {
        $properties = [];
        $required = [];

        foreach ($type->fields() as $field) {
            $definition = $field->toArray();
            $properties[$definition['key']] = $this->fieldSchema($definition);
            if (! empty($definition['required'])) {
                $required[] = $definition['key'];
            }
        }

        if ($type->slug() === 'global-ref') {
            $properties['global'] = ['$ref' => '#/$defs/entity_ref'];
            $required[] = 'global';
        }

        return array_filter([
            'type' => 'object',
            'description' => $type->label(),
            'properties' => (object) $properties,
            'required' => $required ?: null,
            'additionalProperties' => false,
        ], fn ($v) => $v !== null);
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function fieldSchema(array $field): array
    {
        $schema = match ($field['type']) {
            'text', 'textarea', 'rich-text', 'email', 'video-url' => ['type' => 'string'],
            'url' => ['oneOf' => [['type' => 'string'], ['$ref' => '#/$defs/link']]],
            'number' => array_filter(['type' => 'number', 'minimum' => $field['min'] ?? null, 'maximum' => $field['max'] ?? null], fn ($v) => $v !== null),
            'checkbox' => ['type' => 'boolean'],
            'select', 'radio' => ['enum' => array_map('strval', array_keys($field['options'] ?? []))],
            'multi-select' => ['type' => 'array', 'items' => ['enum' => array_map('strval', array_keys($field['options'] ?? []))]],
            'link' => ['$ref' => '#/$defs/link'],
            'image', 'media' => ['$ref' => '#/$defs/image'],
            'icon' => ['type' => 'string', 'pattern' => '^bi-[a-z0-9-]+$'],
            'color' => ['$ref' => '#/$defs/color'],
            'date' => ['type' => 'string', 'format' => 'date'],
            'time' => ['type' => 'string', 'pattern' => '^([01][0-9]|2[0-3]):[0-5][0-9]$'],
            'datetime' => ['type' => 'string'],
            'repeater' => array_filter([
                'type' => 'array',
                'minItems' => $field['min_items'] ?? null,
                'maxItems' => $field['max_items'] ?? null,
                'items' => [
                    'type' => 'object',
                    'properties' => (object) array_column(array_map(fn ($sub) => ['key' => $sub['key'], 'schema' => $this->fieldSchema($sub)], $field['fields'] ?? []), 'schema', 'key'),
                    'additionalProperties' => false,
                ],
            ], fn ($v) => $v !== null),
            default => [],
        };

        if (in_array($field['type'], ['text', 'textarea', 'rich-text', 'email', 'video-url'], true) && isset($field['max'])) {
            $schema['maxLength'] = $field['max'];
        }

        return ['description' => $field['label'] ?? $field['key']] + $schema;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function describeField(array $field): string
    {
        $type = $field['type'];
        if (in_array($type, ['select', 'radio', 'multi-select'], true)) {
            $type .= '('.implode('|', array_map('strval', array_keys($field['options'] ?? []))).')';
        }
        if ($type === 'repeater') {
            $type = 'list of {'.implode(', ', array_map(fn ($sub) => $this->describeField($sub), $field['fields'] ?? [])).'}';
        }

        return $field['key'].':'.$type.(! empty($field['required']) ? '*' : '');
    }

    /**
     * @return array<string, BlockType>
     */
    private function types(bool $includeCustom): array
    {
        return $includeCustom ? $this->registry->all() : $this->registry->core();
    }

    private function defName(string $slug): string
    {
        return str_replace(['/', '-'], ['__', '_'], $slug);
    }
}
