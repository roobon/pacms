<?php

namespace App\Services\Exchange;

use App\Cms\Blocks\BlockTreeRepository;
use App\Cms\Blocks\BlockTreeValidator;
use App\Cms\Exchange\DocumentReader;
use App\Cms\Exchange\ImportCleaner;
use App\Cms\Exchange\ImportReport;
use App\Cms\Exchange\PortableTranslator;
use App\Models\ImportJob;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogger;
use App\Services\Blocks\BlockTemplateService;
use App\Services\Media\MediaService;
use App\Services\Pages\PagePathService;
use App\Services\Pages\PageService;
use App\Support\Html\HtmlSanitizer;
use App\Support\Http\SafeHttpClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * JSON import (CMS-BLOCK-SCHEMA.md §17), in two steps:
 *
 * 1. analyse(): parse, check, translate and clean the document; store an ImportJob with
 *    the report and the asset plan. Nothing is created.
 * 2. run(): after the user confirmed (asset choices, target), download or map assets,
 *    check the tree again with real media, and create a draft (page or template) or add
 *    the blocks to an existing page's working copy. Imports never publish anything.
 */
class ImportService
{
    public function __construct(
        private readonly DocumentReader $reader,
        private readonly PortableTranslator $translator,
        private readonly ImportCleaner $cleaner,
        private readonly SafeHttpClient $http,
        private readonly MediaService $media,
        private readonly PageService $pages,
        private readonly PagePathService $paths,
        private readonly BlockTemplateService $templates,
        private readonly BlockTreeRepository $tree,
        private readonly ActivityLogger $logger,
    ) {}

    public function analyse(string $json, User $user, ?string $sourceName = null): ImportJob
    {
        $report = new ImportReport;
        $document = $this->reader->read($json, $report);

        $translated = [];
        $assets = [];

        if ($document !== null) {
            $assets = $this->assetPlan((array) ($document['assets'] ?? []), $report);
            $keys = array_column(array_filter($assets, fn ($a) => $a['valid']), 'key');
            $kind = (string) $document['kind'];

            $roots = $kind === 'page' ? (array) ($document['page']['blocks'] ?? []) : (array) $document['blocks'];
            $pointer = $kind === 'page' ? '/page/blocks' : '/blocks';
            ['nodes' => $nodes, 'origins' => $origins] = $this->translator->nodes($roots, $pointer, $keys, $report);

            $context = $kind === 'template' ? BlockTreeValidator::CONTEXT_TEMPLATE : BlockTreeValidator::CONTEXT_PAGE;
            $clean = $this->cleaner->clean($nodes, $origins, $report, $user, $context, $keys);

            $translated = [
                'kind' => $kind,
                'blocks' => $clean ?? [],
                'origins' => $origins,
                'page' => $kind === 'page' ? $this->pageFields((array) $document['page'], $keys, $report) : null,
                'template' => $kind === 'template' ? $this->templateFields((array) $document['template'], $report) : null,
            ];

            if ($kind === 'section') {
                foreach ($clean ?? [] as $i => $node) {
                    if ($node['type'] !== 'section') {
                        $report->info('blocks', __('Section documents normally contain "section" blocks at the top level; this one has ":type".', ['type' => $node['type']]), "/blocks/{$i}");
                    }
                }
            }

            $this->summarizeBlocks($clean ?? [], $report, $assets);
        }

        $job = ImportJob::query()->create([
            'user_id' => $user->id,
            'kind' => (string) ($document['kind'] ?? 'unknown'),
            'status' => $report->hasErrors() ? ImportJob::FAILED : ImportJob::AWAITING,
            'title' => mb_substr((string) ($report->toArray()['summary']['title'] ?? $sourceName ?? ''), 0, 255) ?: null,
            'schema_version' => '1.0',
            'source' => mb_strlen($json) <= DocumentReader::MAX_BYTES ? $json : null,
            'document' => json_encode($translated, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'assets' => $assets,
            'report' => $report->toArray(),
        ]);

        $this->logger->log('import.analysed', $job, ['kind' => $job->kind, 'errors' => $report->toArray()['counts']['error']], $user);

        return $job;
    }

    /**
     * Mark a confirmed import as running, exactly once (double-clicks and repeated requests
     * cannot start it twice). Returns false when it was not waiting for confirmation.
     *
     * @param  array<string, mixed>  $options
     */
    public function claim(ImportJob $job, array $options): bool
    {
        return ImportJob::query()->whereKey($job->id)->where('status', ImportJob::AWAITING)
            ->update(['status' => ImportJob::IMPORTING, 'options' => json_encode($options), 'updated_at' => now()]) === 1;
    }

    /**
     * @param  array{target?: string, page_id?: int|null, assets?: array<string, array{strategy?: string, media_id?: int|null}>}  $options
     */
    public function run(ImportJob $job, User $user, array $options): ImportJob
    {
        if ($job->status !== ImportJob::IMPORTING) {
            return $job; // only a job claimed with claim() runs, and only once
        }

        $report = ImportReport::fromArray($job->report);
        $document = $job->documentData();

        try {
            $media = $this->resolveAssets((array) $job->assets, (array) ($options['assets'] ?? []), $user, $report);
            $nodes = $this->substitute((array) $document['blocks'], $media);

            $context = $job->kind === 'template' ? BlockTreeValidator::CONTEXT_TEMPLATE : BlockTreeValidator::CONTEXT_PAGE;
            $clean = $this->cleaner->clean($nodes, (array) ($document['origins'] ?? []), $report, $user, $context, dropIncomplete: true);
            if ($clean === null) {
                throw ValidationException::withMessages(['import' => __('The blocks are not valid after the images were resolved. See the report.')]);
            }

            [$type, $id] = $this->create($job, $document, $clean, $media, $options, $user, $report);

            $job->forceFill([
                'status' => ImportJob::COMPLETED,
                'result_type' => $type,
                'result_id' => $id,
                'report' => $report->toArray(),
            ])->save();
            $this->logger->log('import.completed', $job, ['result' => "{$type}#{$id}"], $user);
        } catch (Throwable $e) {
            $message = $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : __('The import failed: :message', ['message' => $e->getMessage()]);
            $report->error('document', (string) $message);
            $job->forceFill(['status' => ImportJob::FAILED, 'error' => mb_substr((string) $message, 0, 2000), 'report' => $report->toArray()])->save();
            $this->logger->log('import.failed', $job, [], $user);

            if (! $e instanceof ValidationException) {
                report($e);
            }
        }

        return $job;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  list<array<string, mixed>>  $nodes
     * @param  array<string, int|null>  $media
     * @param  array<string, mixed>  $options
     * @return array{0: string, 1: int}
     */
    private function create(ImportJob $job, array $document, array $nodes, array $media, array $options, User $user, ImportReport $report): array
    {
        if ($job->kind === 'page') {
            $fields = (array) $document['page'];
            $parent = isset($fields['parent_id']) ? Page::query()->find($fields['parent_id']) : null;
            $slug = $this->freeSlug((string) ($fields['slug'] ?? '') ?: (string) $fields['title'], $parent);
            if ($slug !== ($fields['slug'] ?? null) && ! empty($fields['slug'])) {
                $report->info('document', __('The URL ":wanted" was taken, so the page uses ":slug".', ['wanted' => $fields['slug'], 'slug' => $slug]), '/page/slug');
            }

            $seo = (array) ($fields['seo'] ?? []);
            if (isset($seo['og_image'])) {
                $seo['og_image_media_id'] = $this->mediaId($seo['og_image'], $media);
                unset($seo['og_image']);
            }

            $page = $this->pages->create($user, [
                'title' => $fields['title'],
                'slug' => $slug,
                'parent_id' => $parent?->id,
                'excerpt' => $fields['excerpt'] ?? null,
                'template' => $fields['template'] ?? 'default',
                'featured_media_id' => isset($fields['featured_image']) ? $this->mediaId($fields['featured_image'], $media) : null,
                'seo' => $seo,
                'blocks' => $nodes,
            ]);
            $report->summarize('created', ['type' => 'page', 'id' => $page->id, 'title' => $page->title, 'path' => '/'.$page->path, 'status' => 'draft']);

            return ['page', $page->id];
        }

        if ($job->kind === 'template' || ($options['target'] ?? '') === 'template') {
            $fields = (array) ($document['template'] ?? []);
            $template = $this->templates->create($user, [
                'name' => $fields['name'] ?? ($job->title ?: __('Imported blocks')),
                'description' => $fields['description'] ?? null,
                'scope' => $fields['scope'] ?? ($job->kind === 'section' ? 'section' : 'block'),
                'category' => $fields['category'] ?? null,
                'status' => 'draft', // imports never offer content to editors until someone decides to
                'blocks' => $nodes,
            ]);
            $report->summarize('created', ['type' => 'template', 'id' => $template->id, 'title' => $template->name, 'status' => 'hidden from the builder until you offer it']);

            return ['block_template', $template->id];
        }

        // Blocks or sections into an existing page: appended to its working copy (a draft change).
        $page = Page::query()->findOrFail((int) ($options['page_id'] ?? 0));
        abort_unless($user->can('update', $page), 403);

        $this->pages->update($user, $page, ['blocks' => [...$this->tree->load($page), ...$nodes]], $page->lock_version);
        $report->summarize('created', ['type' => 'page_blocks', 'id' => $page->id, 'title' => $page->title, 'status' => __('added to the end of the page as unpublished changes')]);

        return ['page', $page->id];
    }

    /**
     * Asset plan (§9): one entry per declared asset with the suggested strategy.
     *
     * @param  array<mixed>  $assets
     * @return list<array<string, mixed>>
     */
    private function assetPlan(array $assets, ImportReport $report): array
    {
        $plan = [];
        $seen = [];

        foreach ($assets as $i => $asset) {
            $pointer = "/assets/{$i}";
            $key = is_array($asset) && is_string($asset['key'] ?? null) ? $asset['key'] : '';

            if (! preg_match('/^[A-Za-z0-9_-]{1,64}$/', $key) || isset($seen[$key])) {
                $report->warning('assets', __('Asset needs a unique "key" (letters, digits, - and _); skipped.'), $pointer);

                continue;
            }
            $seen[$key] = true;

            $url = is_string($asset['url'] ?? null) ? trim($asset['url']) : null;
            $mediaId = is_numeric($asset['media'] ?? null) ? (int) $asset['media'] : null;
            $strategy = in_array($asset['strategy'] ?? null, ['download', 'external', 'existing'], true) ? $asset['strategy'] : ($mediaId ? 'existing' : 'download');
            $valid = true;

            if ($strategy === 'external') {
                $report->info('assets', __('Asset ":key": external images are downloaded into the Media Library (this site does not hot-link images).', ['key' => $key]), $pointer, $key);
                $strategy = 'download';
            }

            if ($strategy === 'existing' && ($mediaId === null || ! Media::query()->whereKey($mediaId)->exists())) {
                $report->warning('assets', __('Asset ":key": media #:id does not exist on this site. Choose a file in the preview or the image stays empty; nothing is substituted.', ['key' => $key, 'id' => $mediaId ?? '?']), $pointer, $key);
                $mediaId = null;
                $strategy = $url ? 'download' : 'skip';
            }

            if ($strategy === 'download' && (! $url || ! preg_match('#^https?://#i', $url))) {
                $report->warning('assets', __('Asset ":key" has no http(s) URL to download.', ['key' => $key]), "{$pointer}/url", $key);
                $strategy = 'skip';
            }

            $plan[] = [
                'key' => $key,
                'url' => $url,
                'media' => $mediaId,
                'alt' => is_scalar($asset['alt'] ?? null) ? mb_substr((string) $asset['alt'], 0, 255) : null,
                'credit' => is_scalar($asset['credit'] ?? null) ? mb_substr((string) $asset['credit'], 0, 255) : null,
                'strategy' => $strategy,
                'valid' => true,
            ];
        }

        return $plan;
    }

    /**
     * Download or map every asset as the user chose. key => media id, or null (left empty).
     *
     * @param  list<array<string, mixed>>  $plan
     * @param  array<string, array{strategy?: string, media_id?: int|null}>  $choices
     * @return array<string, int|null>
     */
    private function resolveAssets(array $plan, array $choices, User $user, ImportReport $report): array
    {
        $ids = [];

        foreach ($plan as $asset) {
            $key = (string) $asset['key'];
            $choice = $choices[$key] ?? [];
            $strategy = $choice['strategy'] ?? $asset['strategy'];
            $ids[$key] = null;

            if ($strategy === 'existing') {
                $id = (int) ($choice['media_id'] ?? $asset['media'] ?? 0);
                $media = Media::query()->find($id);
                if ($media === null || ! $media->isPublic()) {
                    $report->warning('assets', __('Asset ":key": media #:id is not available; the image was left empty.', ['key' => $key, 'id' => $id]), '', $key);
                } else {
                    $ids[$key] = $media->id;
                    if (($choice['media_id'] ?? null) && (int) $choice['media_id'] !== (int) $asset['media']) {
                        $report->info('assets', __('Asset ":key" was mapped to existing media #:id by :user.', ['key' => $key, 'id' => $id, 'user' => $user->name]), '', $key);
                    }
                }

                continue;
            }

            if ($strategy !== 'download' || empty($asset['url'])) {
                $report->info('assets', __('Asset ":key" was skipped; its image stays empty.', ['key' => $key]), '', $key);

                continue;
            }

            try {
                $file = $this->http->download((string) $asset['url']);
                try {
                    $upload = new UploadedFile($file['path'], $file['name'], $file['mime'], null, true);
                    // Re-importing the same image reuses the existing library item.
                    $media = $this->media->findDuplicate($upload) ?? $this->media->store(
                        $upload,
                        $user,
                        array_filter(['alt' => $asset['alt'], 'credit' => $asset['credit']]),
                    );
                    $ids[$key] = $media->id;
                    $report->info('assets', __('Asset ":key" was downloaded as media #:id.', ['key' => $key, 'id' => $media->id]), '', $key);
                } finally {
                    @unlink($file['path']);
                }
            } catch (Throwable $e) {
                $reason = $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage();
                $report->warning('assets', __('Asset ":key" could not be imported (:reason); the image was left empty.', ['key' => $key, 'reason' => $reason]), '', $key);
            }
        }

        return $ids;
    }

    /**
     * Replace {"$asset": key} with {"$media": id}; assets without media are removed so the
     * field stays empty (never substituted).
     *
     * @param  array<mixed>  $value
     * @param  array<string, int|null>  $media
     * @return array<mixed>
     */
    private function substitute(array $value, array $media): array
    {
        foreach ($value as $k => $item) {
            if (! is_array($item)) {
                continue;
            }
            if (array_keys($item) === ['$asset']) {
                $id = $media[(string) $item['$asset']] ?? null;
                if ($id === null) {
                    unset($value[$k]);
                } else {
                    $value[$k] = ['$media' => $id];
                }

                continue;
            }
            $value[$k] = $this->substitute($item, $media);
        }

        return $value;
    }

    /**
     * @param  array<string, int|null>  $media
     */
    private function mediaId(mixed $value, array $media): ?int
    {
        if (is_array($value) && isset($value['$asset'])) {
            return $media[(string) $value['$asset']] ?? null;
        }

        $id = is_array($value) ? (int) ($value['$media'] ?? 0) : 0;

        return $id > 0 && Media::query()->whereKey($id)->exists() ? $id : null;
    }

    /**
     * @param  array<string, mixed>  $page
     * @param  list<string>  $assetKeys
     * @return array<string, mixed>
     */
    private function pageFields(array $page, array $assetKeys, ImportReport $report): array
    {
        $title = is_scalar($page['title'] ?? null) ? trim(HtmlSanitizer::plain((string) $page['title'])) : '';
        if ($title === '' || mb_strlen($title) > 255) {
            $report->error('document', __('The page needs a "title" (up to 255 characters).'), '/page/title');
        }

        $fields = [
            'title' => mb_substr($title, 0, 255),
            'slug' => is_string($page['slug'] ?? null) ? $this->paths->slugify($page['slug']) : null,
            // Summaries may carry bold, italic and links (same cleaning as the page form).
            'excerpt' => is_scalar($page['excerpt'] ?? null) ? (app(HtmlSanitizer::class)->inline(mb_substr((string) $page['excerpt'], 0, 5000)) ?: null) : null,
            'template' => in_array($page['template'] ?? 'default', array_keys(config('pacms.pages.templates')), true) ? ($page['template'] ?? 'default') : 'default',
        ];

        if (isset($page['template']) && $fields['template'] !== $page['template']) {
            $report->warning('unsupported', __('Page layout ":template" does not exist; "default" is used.', ['template' => (string) json_encode($page['template'])]), '/page/template');
        }

        if (isset($page['parent'])) {
            $parent = PortableTranslator::findPage((array) ($page['parent']['$ref'] ?? []));
            $parent === null
                ? $report->warning('references', __('Parent page was not found; the page is created at the top level.'), '/page/parent')
                : $fields['parent_id'] = $parent->id;
        }

        if (isset($page['featured_image'])) {
            $fields['featured_image'] = $this->translator->media($page['featured_image'], '/page/featured_image', $assetKeys, new ImportReport);
        }

        foreach (['header', 'footer'] as $part) {
            if (array_key_exists($part, $page)) {
                $report->info('unsupported', __('Page :part choice is not available yet (navigation arrives in Phase 9); the site default is used.', ['part' => $part]), "/page/{$part}");
            }
        }

        $seo = (array) ($page['seo'] ?? []);
        $fields['seo'] = array_filter([
            'title' => is_scalar($seo['title'] ?? null) ? mb_substr(HtmlSanitizer::plain((string) $seo['title']), 0, 255) : null,
            'description' => is_scalar($seo['description'] ?? null) ? mb_substr(HtmlSanitizer::plain((string) $seo['description']), 0, 500) : null,
            'robots_index' => isset($seo['robots']['index']) ? (bool) $seo['robots']['index'] : null,
            'robots_follow' => isset($seo['robots']['follow']) ? (bool) $seo['robots']['follow'] : null,
            'og_title' => is_scalar($seo['og']['title'] ?? null) ? mb_substr(HtmlSanitizer::plain((string) $seo['og']['title']), 0, 255) : null,
            'og_description' => is_scalar($seo['og']['description'] ?? null) ? mb_substr(HtmlSanitizer::plain((string) $seo['og']['description']), 0, 500) : null,
            'og_image' => isset($seo['og']['image']) ? $this->translator->media($seo['og']['image'], '/page/seo/og/image', $assetKeys, new ImportReport) : null,
        ], fn ($v) => $v !== null);

        foreach (array_diff(array_keys($page), ['title', 'slug', 'parent', 'excerpt', 'featured_image', 'template', 'header', 'footer', 'seo', 'blocks']) as $unknown) {
            $report->warning('unsupported', __('Unsupported property ":name" was ignored.', ['name' => $unknown]), "/page/{$unknown}");
        }

        return array_filter($fields, fn ($v) => $v !== null);
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    private function templateFields(array $template, ImportReport $report): array
    {
        $name = is_scalar($template['name'] ?? null) ? trim(HtmlSanitizer::plain((string) $template['name'])) : '';
        if ($name === '') {
            $report->error('document', __('The template needs a "name".'), '/template/name');
        }

        return array_filter([
            'name' => mb_substr($name, 0, 255),
            'description' => is_scalar($template['description'] ?? null) ? mb_substr(HtmlSanitizer::plain((string) $template['description']), 0, 1000) : null,
            'scope' => in_array($template['scope'] ?? null, ['block', 'section', 'page'], true) ? $template['scope'] : 'section',
            'category' => is_scalar($template['category'] ?? null) ? mb_substr((string) $template['category'], 0, 64) : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function freeSlug(string $wanted, ?Page $parent): string
    {
        $base = $this->paths->slugify($wanted) ?: 'imported-page';
        $candidate = $base;
        $n = 2;

        while (($parent === null && in_array($candidate, config('pacms.pages.reserved_slugs'), true))
            || Page::withTrashed()->where('path', $this->paths->pathFor($parent, $candidate))->exists()) {
            $candidate = "{$base}-{$n}";
            $n++;
        }

        return $candidate;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $assets
     */
    private function summarizeBlocks(array $nodes, ImportReport $report, array $assets): void
    {
        $types = [];
        $dynamic = 0;
        $walk = function (array $nodes) use (&$walk, &$types, &$dynamic) {
            foreach ($nodes as $node) {
                $types[$node['type']] = ($types[$node['type']] ?? 0) + 1;
                if (($node['source']['mode'] ?? null) === 'dynamic') {
                    $dynamic++;
                }
                $walk($node['children'] ?? []);
            }
        };
        $walk($nodes);
        arsort($types);

        $report->summarize('blocks', array_sum($types));
        $report->summarize('block_types', $types);
        $report->summarize('dynamic_blocks', $dynamic);
        $report->summarize('assets', count($assets));
        $report->summarize('assets_to_download', count(array_filter($assets, fn ($a) => $a['strategy'] === 'download')));
        $report->summarize('used_assets', array_values(array_unique(Arr::flatten(array_map(fn ($a) => $a['key'], $assets)))));
    }
}
