<?php

namespace App\Cms\Sources;

use App\Cms\Blocks\BlockType;
use App\Cms\Blocks\Types\ContentTypeBlock;
use App\Cms\Content\ContentTypeRegistry;
use App\Cms\Validation\ValueValidator;

/**
 * Resolves and validates block sources: static (content.items), dynamic (whitelisted
 * CMS queries) and external (registered providers). All three produce the same
 * normalised item shape, so display modes never know where items came from.
 */
class SourceRegistry
{
    /** @var array<string, DynamicSource> */
    private array $dynamic = [];

    /** @var array<string, ExternalProvider> */
    private array $external = [];

    public function __construct(ContentTypeRegistry $content)
    {
        // Every content module can feed dynamic blocks.
        foreach ($content->all() as $type) {
            $this->registerDynamic(new ContentSource($type));
        }
        // Testimonials have no pages, so they are not a content module, but blocks list them too.
        $this->registerDynamic(new TestimonialSource);
    }

    public function registerDynamic(DynamicSource $source): void
    {
        $this->dynamic[$source->entity()] = $source;
    }

    public function registerExternal(ExternalProvider $provider): void
    {
        $this->external[$provider->key()] = $provider;
    }

    public function dynamic(string $entity): ?DynamicSource
    {
        // Content types made in the admin after this registry was built.
        if (! isset($this->dynamic[$entity]) && ($type = app(ContentTypeRegistry::class)->find($entity)) !== null) {
            $this->registerDynamic(new ContentSource($type));
        }

        return $this->dynamic[$entity] ?? null;
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    public function validate(BlockType $type, array $source, ValueValidator $values, string $path): array
    {
        $modes = $type->sourceModes();
        $mode = $source['mode'] ?? $modes[0];

        if (! in_array($mode, $modes, true)) {
            $values->errors()->add("{$path}.mode", __('This block cannot use :mode content.', ['mode' => $mode]));

            return [];
        }

        if ($mode === 'static') {
            return count($modes) > 1 ? ['mode' => 'static'] : [];
        }

        if ($mode === 'dynamic') {
            $dynamic = $this->dynamic((string) $type->dynamicEntity());
            if ($dynamic === null && $type instanceof ContentTypeBlock && ! $type->active()) {
                // A disabled type's block stays on its page (showing nothing) until removed.
                return ['mode' => 'dynamic', 'provider' => 'cms', 'entity' => $type->dynamicEntity()];
            }
            if ($dynamic === null) {
                $values->errors()->add("{$path}.entity", __('Dynamic content is not available for this block.'));

                return [];
            }

            return $dynamic->validate($source, $values, $path);
        }

        $provider = $this->external[(string) ($source['provider'] ?? '')] ?? null;
        if ($provider === null || ! is_string($source['source'] ?? null) || ! $provider->hasSource($source['source'])) {
            $values->errors()->add("{$path}.source", __('Choose a connected external source.'));

            return [];
        }

        return [
            'mode' => 'external',
            'provider' => $provider->key(),
            'source' => $source['source'],
            'limit' => max(1, min(50, (int) ($source['limit'] ?? 6))),
        ];
    }

    /**
     * Items for a validated source, or null for static/no source.
     *
     * @param  array<string, mixed>  $source
     * @return list<array<string, mixed>>|null
     */
    public function items(array $source): ?array
    {
        return match ($source['mode'] ?? 'static') {
            'dynamic' => $this->dynamic((string) ($source['entity'] ?? ''))?->items($source) ?? [],
            'external' => ($provider = $this->external[$source['provider'] ?? ''] ?? null)
                ? $provider->items((string) $source['source'], (int) ($source['limit'] ?? 6))
                : [],
            default => null,
        };
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function describeDynamic(): array
    {
        return array_map(fn (DynamicSource $source) => $source->describe(), $this->dynamic);
    }
}
