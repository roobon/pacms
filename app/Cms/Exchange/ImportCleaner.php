<?php

namespace App\Cms\Exchange;

use App\Cms\Blocks\BlockRegistry;
use App\Cms\Blocks\BlockTreeValidator;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Import stages 4–7 (CMS-BLOCK-SCHEMA.md §16) using the same BlockTreeValidator as the
 * builder, so imports get exactly the same security rules:
 *
 * - an invalid value is removed and reported as a warning (the field is left empty)
 * - a block that cannot be placed is removed and reported
 * - what cannot be fixed by removing (a required field) is an error and stops the import
 * - values the validator dropped or changed (unsupported options, cleaned HTML) are
 *   reported by comparing the input with the clean result
 */
final class ImportCleaner
{
    private const SECTIONS = ['content', 'source', 'display', 'layout', 'style', 'responsive', 'advanced'];

    private const MAX_PASSES = 8;

    public function __construct(
        private readonly BlockTreeValidator $validator,
        private readonly BlockRegistry $registry,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $nodes  translated (PortableTranslator)
     * @param  array<string, array{pointer: string, key: string|null}>  $origins
     * @param  list<string>  $pendingAssets
     * @param  bool  $dropIncomplete  after confirmation: a block whose required value is missing
     *                                (e.g. its image failed to download) is left out instead of
     *                                stopping the whole import
     * @return list<array<string, mixed>>|null clean nodes, or null when errors remain
     */
    public function clean(array $nodes, array $origins, ImportReport $report, User $user, string $context = BlockTreeValidator::CONTEXT_PAGE, array $pendingAssets = [], bool $dropIncomplete = false): ?array
    {
        $validator = $this->validator->withPendingAssets($pendingAssets);

        for ($pass = 0; $pass < self::MAX_PASSES; $pass++) {
            try {
                $clean = $validator->validate($nodes, $user, $context);
                $this->reportDifferences($this->index($nodes), $this->index($clean), $origins, $report);

                return $clean;
            } catch (ValidationException $e) {
                $fixed = false;

                foreach ($e->errors() as $path => $messages) {
                    if ($this->fix($nodes, $path, $messages[0] ?? '', $origins, $report, $dropIncomplete)) {
                        $fixed = true;
                    }
                }

                if (! $fixed || $report->hasErrors()) {
                    return null;
                }
            }
        }

        $report->error('blocks', __('The blocks could not be cleaned up automatically.'));

        return null;
    }

    /**
     * Remove what one validation error points at. Returns false (and reports an error)
     * when nothing can be removed.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @param  array<string, array{pointer: string, key: string|null}>  $origins
     */
    private function fix(array &$nodes, string $path, string $message, array $origins, ImportReport $report, bool $dropIncomplete = false): bool
    {
        if (! preg_match('/^blocks\.([0-9a-z]{26})(?:\.(.+))?$/', $path, $m)) {
            $report->error('blocks', $message);

            return false;
        }

        [$uuid, $field] = [$m[1], $m[2] ?? ''];
        $origin = $origins[$uuid] ?? ['pointer' => '', 'key' => null];

        if ($field === '') {
            $removed = $this->removeNode($nodes, $uuid);
            $report->warning('blocks', __('Block removed: :message', ['message' => $message]), $origin['pointer'], $origin['key']);

            return $removed;
        }

        $removed = false;
        $nodes = $this->mapNode($nodes, $uuid, function (array $node) use ($field, &$removed) {
            if (Arr::has($node, $field)) {
                Arr::forget($node, $field);
                $removed = true;
            }

            return $node;
        });

        // A design setting that is incomplete without the removed value (e.g. an image
        // background whose image is gone) is removed as a whole, rather than the block.
        $segments = explode('.', $field);
        while (! $removed && count($segments) > 2 && $segments[0] !== 'content') {
            array_pop($segments);
            $parent = implode('.', $segments);
            $nodes = $this->mapNode($nodes, $uuid, function (array $node) use ($parent, &$removed) {
                if (Arr::has($node, $parent)) {
                    Arr::forget($node, $parent);
                    $removed = true;
                }

                return $node;
            });
        }

        $pointer = $origin['pointer'].'/'.str_replace('.', '/', $field);
        if ($removed) {
            $section = preg_match('/media|file|exists|link|global/i', $message) ? 'references' : 'blocks';
            $report->warning($section, __(':message The value was removed.', ['message' => rtrim($message, '.').'.']), $pointer, $origin['key']);
        } elseif ($dropIncomplete) {
            $removed = $this->removeNode($nodes, $uuid);
            $report->warning('blocks', __('Block removed: :message', ['message' => $message]), $pointer, $origin['key']);
        } else {
            $report->error('blocks', $message, $pointer, $origin['key']);
        }

        return $removed;
    }

    /**
     * @param  array<string, array<string, mixed>>  $before  uuid => node (without children)
     * @param  array<string, array<string, mixed>>  $after
     * @param  array<string, array{pointer: string, key: string|null}>  $origins
     */
    private function reportDifferences(array $before, array $after, array $origins, ImportReport $report): void
    {
        foreach ($before as $uuid => $node) {
            $origin = $origins[$uuid] ?? ['pointer' => '', 'key' => null];
            $clean = $after[$uuid] ?? null;
            if ($clean === null) {
                continue;
            }

            $richText = array_column(array_filter(
                array_map(fn ($f) => $f->toArray(), $this->registry->find((string) $node['type'])?->fields() ?? []),
                fn ($f) => $f['type'] === 'rich-text',
            ), 'key');

            foreach (self::SECTIONS as $section) {
                $old = Arr::dot((array) ($node[$section] ?? []));
                $new = Arr::dot((array) ($clean[$section] ?? []));

                foreach ($old as $path => $value) {
                    $pointer = "{$origin['pointer']}/{$section}/".str_replace('.', '/', (string) $path);

                    if (! array_key_exists($path, $new) && $value !== null && $value !== [] && $value !== '') {
                        $report->warning('unsupported', __('Removed: not supported or not valid here.'), $pointer, $origin['key']);
                    } elseif ($section === 'content' && in_array(explode('.', (string) $path)[0], $richText, true) && is_string($value) && $this->normalise($value) !== $this->normalise((string) $new[$path])) {
                        $report->warning('security', __('The HTML was cleaned: only safe formatting is kept (scripts, styles, event handlers and unsafe links are removed).'), $pointer, $origin['key']);
                    } elseif (is_string($value) && preg_match('/<\s*script|javascript:|on[a-z]+\s*=/i', $value)) {
                        $report->info('security', __('Text that looks like code is shown as plain text, never run.'), $pointer, $origin['key']);
                    }
                }
            }
        }
    }

    private function normalise(string $html): string
    {
        return (string) preg_replace('/\s+/', ' ', trim(html_entity_decode(strip_tags($html, '<p><br><strong><em><a><ul><ol><li><h2><h3><h4><h5><h6><blockquote>'))));
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, array<string, mixed>>
     */
    private function index(array $nodes): array
    {
        $out = [];
        foreach ($nodes as $node) {
            $out[(string) $node['uuid']] = Arr::except($node, ['children']);
            $out += $this->index($node['children'] ?? []);
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private function removeNode(array &$nodes, string $uuid): bool
    {
        foreach ($nodes as $i => $node) {
            if ($node['uuid'] === $uuid) {
                array_splice($nodes, $i, 1);

                return true;
            }
            if (! empty($node['children'])) {
                $children = $node['children'];
                if ($this->removeNode($children, $uuid)) {
                    $nodes[$i]['children'] = $children;

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  callable(array<string, mixed>): array<string, mixed>  $change
     * @return list<array<string, mixed>>
     */
    private function mapNode(array $nodes, string $uuid, callable $change): array
    {
        return array_map(function (array $node) use ($uuid, $change) {
            if ($node['uuid'] === $uuid) {
                return $change($node);
            }
            if (! empty($node['children'])) {
                $node['children'] = $this->mapNode($node['children'], $uuid, $change);
            }

            return $node;
        }, $nodes);
    }
}
