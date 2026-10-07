<?php

namespace App\Cms\Exchange;

/**
 * Import stages 1–3 (CMS-BLOCK-SCHEMA.md §16): parse and limits, envelope, schema version.
 * Returns the decoded document, or null when the import cannot continue (errors reported).
 */
final class DocumentReader
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    public const MAX_NODES = 2000;

    public const MAX_DEPTH = 12;

    public const MAX_ASSETS = 200;

    public const KINDS = ['block', 'section', 'template', 'page'];

    /**
     * @return array<string, mixed>|null
     */
    public function read(string $json, ImportReport $report): ?array
    {
        if (strlen($json) > self::MAX_BYTES) {
            $report->error('document', __('The file is larger than 2 MB.'));

            return null;
        }

        if (! mb_check_encoding($json, 'UTF-8')) {
            $report->error('document', __('The file is not valid UTF-8 text.'));

            return null;
        }

        // Some AI tools wrap JSON in a Markdown code fence; accept that, nothing else.
        $json = (string) preg_replace('/^\s*```(?:json)?\s*\n(.*)\n\s*```\s*$/s', '$1', $json);

        try {
            $document = json_decode($json, true, 128, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $report->error('document', __('This is not valid JSON: :message.', ['message' => $e->getMessage()]));

            return null;
        }

        if (! is_array($document) || array_is_list($document)) {
            $report->error('document', __('The document must be a JSON object (the PACMS envelope).'));

            return null;
        }

        $document = $this->version($document, $report);
        if ($document === null) {
            return null;
        }

        $kind = $document['kind'] ?? null;
        if ($kind === 'site') {
            $report->error('document', __('Full-site packages ("kind": "site") are not supported in schema 1.0.'), '/kind');

            return null;
        }
        if (! in_array($kind, self::KINDS, true)) {
            $report->error('document', __('"kind" must be one of: :kinds.', ['kinds' => implode(', ', self::KINDS)]), '/kind');

            return null;
        }

        $roots = $kind === 'page' ? ($document['page']['blocks'] ?? []) : ($document['blocks'] ?? null);
        $rootPointer = $kind === 'page' ? '/page/blocks' : '/blocks';

        if ($kind === 'page' && ! is_array($document['page'] ?? null)) {
            $report->error('document', __('A page document needs a "page" object.'), '/page');

            return null;
        }
        if ($kind === 'template' && ! is_array($document['template'] ?? null)) {
            $report->error('document', __('A template document needs a "template" object with a name.'), '/template');

            return null;
        }
        if (! is_array($roots) || ! array_is_list($roots) || ($kind !== 'page' && $roots === [])) {
            $report->error('document', __('":pointer" must be a list of blocks.', ['pointer' => $rootPointer]), $rootPointer);

            return null;
        }

        [$nodes, $depth] = $this->measure($roots, 1);
        if ($nodes > self::MAX_NODES) {
            $report->error('document', __('The document has :count blocks; the limit is :max.', ['count' => $nodes, 'max' => self::MAX_NODES]), $rootPointer);

            return null;
        }
        if ($depth > self::MAX_DEPTH) {
            $report->error('document', __('Blocks are nested :depth levels deep; the limit is :max.', ['depth' => $depth, 'max' => self::MAX_DEPTH]), $rootPointer);

            return null;
        }

        $assets = $document['assets'] ?? [];
        if (! is_array($assets) || ! array_is_list($assets) || count($assets) > self::MAX_ASSETS) {
            $report->error('document', __('"assets" must be a list of at most :max items.', ['max' => self::MAX_ASSETS]), '/assets');

            return null;
        }

        foreach (array_diff(array_keys($document), ['schema_version', 'kind', 'meta', 'assets', 'page', 'blocks', 'template', 'custom_block_types']) as $unknown) {
            $report->warning('unsupported', __('Unsupported property ":name" was ignored.', ['name' => $unknown]), '/'.$unknown);
        }

        if (! empty($document['custom_block_types'])) {
            $report->warning('unsupported', __('Custom block type definitions are not imported in 1.0. Create the types under Design → Custom blocks first; blocks of existing types import normally.'), '/custom_block_types');
        }

        $report->summarize('kind', $kind);
        $report->summarize('schema_version', '1.0');
        $report->summarize('title', $this->title($document));
        $report->summarize('generator', is_scalar($document['meta']['generator'] ?? null) ? mb_substr((string) $document['meta']['generator'], 0, 120) : null);
        $report->summarize('notes', is_scalar($document['meta']['notes'] ?? null) ? mb_substr((string) $document['meta']['notes'], 0, 2000) : null);

        return $document;
    }

    /**
     * Schema version check and migration (§19). 1.0 is current; newer minors are read as
     * 1.0 with unknown items reported; other majors are refused.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>|null
     */
    private function version(array $document, ImportReport $report): ?array
    {
        $version = $document['schema_version'] ?? null;

        if (! is_string($version) || ! preg_match('/^(\d+)\.(\d+)$/', $version, $m)) {
            $report->error('document', __('"schema_version" is missing. Add "schema_version": "1.0".'), '/schema_version');

            return null;
        }

        if ((int) $m[1] !== 1) {
            $report->error('document', __('Schema version :version is not supported by this site (supported: 1.x).', ['version' => $version]), '/schema_version');

            return null;
        }

        if ((int) $m[2] > 0) {
            $report->warning('document', __('The document uses schema :version, newer than this site (1.0). Items this site does not know are reported and skipped.', ['version' => $version]), '/schema_version');
        }

        return $document;
    }

    /**
     * @param  array<mixed>  $nodes
     * @return array{0: int, 1: int} node count, depth
     */
    private function measure(array $nodes, int $level): array
    {
        $count = 0;
        $depth = $nodes === [] ? 0 : $level;

        foreach ($nodes as $node) {
            $count++;
            $children = is_array($node) && is_array($node['children'] ?? null) ? $node['children'] : [];
            if ($children !== []) {
                [$childCount, $childDepth] = $this->measure($children, $level + 1);
                $count += $childCount;
                $depth = max($depth, $childDepth);
            }
        }

        return [$count, $depth];
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function title(array $document): ?string
    {
        $title = $document['meta']['title'] ?? $document['page']['title'] ?? $document['template']['name'] ?? null;

        return is_scalar($title) ? mb_substr((string) $title, 0, 255) : null;
    }
}
