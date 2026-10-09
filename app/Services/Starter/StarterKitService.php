<?php

namespace App\Services\Starter;

use App\Cms\Blocks\BlockTreeRepository;
use App\Cms\Blocks\BlockTreeValidator;
use App\Cms\Exchange\DocumentReader;
use App\Cms\Exchange\ImportCleaner;
use App\Cms\Exchange\ImportReport;
use App\Cms\Exchange\PortableTranslator;
use App\Models\BlockTemplate;
use App\Models\GlobalBlock;
use App\Models\Page;
use App\Models\User;
use App\Services\Blocks\BlockTemplateService;
use App\Services\Blocks\GlobalBlockService;
use App\Services\Navigation\MenuService;
use App\Services\Pages\PageService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The starter kit (Phase 8): designed page and section templates and global blocks that a
 * new site starts from. They are files in the JSON import format (resources/starter-kit),
 * read through the same reader, translator and cleaner as an import, so the kit also
 * proves that format.
 *
 * - install() adds what is missing and never changes what exists (edited copies stay).
 * - restore() puts shipped templates back to their original design.
 * - Pages are created only on request, as drafts, and never over existing pages.
 */
class StarterKitService
{
    public function __construct(
        private readonly DocumentReader $reader,
        private readonly PortableTranslator $translator,
        private readonly ImportCleaner $cleaner,
        private readonly BlockTemplateService $templates,
        private readonly GlobalBlockService $globals,
        private readonly PageService $pages,
        private readonly BlockTreeRepository $tree,
        private readonly SettingsService $settings,
    ) {}

    public static function path(string $file = ''): string
    {
        return resource_path('starter-kit'.($file === '' ? '' : '/'.$file));
    }

    /**
     * The kit's manifest: global blocks, templates (in palette order) and pages.
     *
     * @return array{globals: array<string, array<string, string>>, templates: list<string>, pages: list<array<string, mixed>>}
     */
    public function manifest(): array
    {
        return require self::path('kit.php');
    }

    /**
     * Install what is missing. Returns what happened: [kind, name, result] rows.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public function install(User $user, bool $pages = false): array
    {
        $manifest = $this->manifest();
        // Menus first: the kit's header and footer show them.
        $done = app(MenuService::class)->prefill($user);
        $done = [...$done, ...$this->installGlobals($user)];
        $done = [...$done, ...$this->chooseHeaderAndFooter($user)];

        foreach ($manifest['templates'] as $file) {
            $document = $this->blocks($file, $user, BlockTreeValidator::CONTEXT_TEMPLATE);
            $slug = (string) $document['template']['slug'];
            if (BlockTemplate::withTrashed()->where('slug', $slug)->exists()) {
                $done[] = ['Template', (string) $document['template']['name'], 'already there'];

                continue;
            }
            $template = $this->templates->create($user, $document['template'] + ['blocks' => $document['blocks']]);
            $template->forceFill(['is_system' => true])->save();
            $done[] = ['Template', $template->name, 'installed'];
        }

        if ($pages) {
            foreach ($manifest['pages'] as $definition) {
                $done[] = ['Page', (string) $definition['title'], $this->page($user, $definition)];
            }
        }

        return $done;
    }

    /**
     * The kit's header and footer become the site's when none is chosen yet.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function chooseHeaderAndFooter(User $user): array
    {
        $done = [];
        foreach (['header' => 'main-header', 'footer' => 'main-footer'] as $role => $slug) {
            if ($this->settings->get('navigation', "{$role}_global_block_id")) {
                continue;
            }
            $id = GlobalBlock::query()->where('slug', $slug)->where('kind', $role)->whereNotNull('published_revision_id')->value('id');
            if ($id) {
                $this->settings->set('navigation', ["{$role}_global_block_id" => (int) $id], $user);
                $done[] = ['Setting', ucfirst($role), 'set to the starter kit\'s'];
            }
        }

        return $done;
    }

    /**
     * The kit's global blocks that are missing (templates place them, so they come first).
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function installGlobals(User $user): array
    {
        $done = [];
        foreach ($this->manifest()['globals'] as $slug => $definition) {
            $existing = GlobalBlock::withTrashed()->where('slug', $slug)->first();
            if ($existing?->trashed()) {
                // The kit's templates place it, so a deleted one comes back.
                $existing->restore();
                $done[] = ['Global block', $existing->name, 'restored from the bin'];

                continue;
            }
            if ($existing !== null) {
                $done[] = ['Global block', $existing->name, 'already there'];

                continue;
            }
            $global = $this->globals->create($user, [
                'name' => $definition['name'],
                'slug' => $slug,
                'description' => $definition['description'] ?? null,
                'kind' => $definition['kind'] ?? 'generic',
                'blocks' => $this->blocks($definition['file'], $user, BlockTreeValidator::CONTEXT_PAGE)['blocks'],
            ]);
            $this->globals->publish($user, $global);
            $done[] = ['Global block', $global->name, 'installed'];
        }

        return $done;
    }

    /**
     * Put shipped templates back to their original design (all, or the one with $slug).
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public function restore(User $user, ?string $slug = null): array
    {
        // Templates place the kit's global blocks (and the header and footer show the menus);
        // put back any that were deleted.
        app(MenuService::class)->prefill($user);
        $done = array_values(array_filter($this->installGlobals($user), fn (array $row) => $row[2] !== 'already there'));
        $found = false;
        foreach ($this->manifest()['templates'] as $file) {
            $document = $this->blocks($file, $user, BlockTreeValidator::CONTEXT_TEMPLATE);
            $fields = $document['template'];
            if ($slug !== null && $fields['slug'] !== $slug) {
                continue;
            }
            $found = true;

            $template = BlockTemplate::withTrashed()->where('slug', $fields['slug'])->first();
            if ($template === null) {
                $template = $this->templates->create($user, $fields + ['blocks' => $document['blocks']]);
                $template->forceFill(['is_system' => true])->save();
                $done[] = ['Template', $template->name, 'installed'];

                continue;
            }
            if ($template->trashed()) {
                $template->restore();
            }
            $this->templates->update($user, $template, array_diff_key($fields, ['slug' => true]) + ['status' => 'published', 'blocks' => $document['blocks']], (int) $template->lock_version);
            $template->forceFill(['is_system' => true])->save();
            $done[] = ['Template', $template->name, 'restored'];
        }

        if ($slug !== null && ! $found) {
            throw new RuntimeException("The starter kit has no template \"{$slug}\".");
        }

        return $done;
    }

    /**
     * A new page as a copy of a page template (the "Start from" choice, and --pages).
     *
     * @return list<array<string, mixed>>
     */
    public function copyOf(BlockTemplate $template): array
    {
        return self::withoutUuids($this->tree->load($template));
    }

    /**
     * Block trees without their ids, so the copy gets new ones.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    public static function withoutUuids(array $nodes): array
    {
        return array_map(function (array $node) {
            unset($node['uuid']);
            if (! empty($node['children'])) {
                $node['children'] = self::withoutUuids($node['children']);
            }

            return $node;
        }, $nodes);
    }

    /**
     * @param  array<string, mixed>  $definition  title, slug, template, excerpt?, home?
     */
    private function page(User $user, array $definition): string
    {
        $slug = (string) $definition['slug'];
        if (Page::withTrashed()->whereNull('parent_id')->where('slug', $slug)->exists()) {
            return 'already there';
        }
        $template = BlockTemplate::query()->where('slug', $definition['template'])->first();
        if ($template === null) {
            return 'skipped (template missing)';
        }

        $page = DB::transaction(function () use ($user, $definition, $slug, $template) {
            return $this->pages->create($user, [
                'title' => $definition['title'],
                'slug' => $slug,
                'excerpt' => $definition['excerpt'] ?? null,
                'template' => $definition['layout'] ?? 'default',
                'blocks' => $this->copyOf($template),
            ]);
        });

        // The home page setting points at the starter Home only when none is chosen yet.
        if (! empty($definition['home']) && ! $this->settings->get('site', 'homepage_page_id')) {
            $this->settings->set('site', ['homepage_page_id' => $page->id], $user);

            return 'created as a draft (home page)';
        }

        return 'created as a draft';
    }

    /**
     * Read one kit file through the import pipeline. A kit file that does not pass cleanly
     * (errors, or warnings about anything removed) is a bug in the kit, so it stops the
     * install with the report's first problem.
     *
     * @return array{blocks: list<array<string, mixed>>, template: array<string, mixed>}
     */
    private function blocks(string $file, User $user, string $context): array
    {
        $report = new ImportReport;
        $document = $this->reader->read((string) file_get_contents(self::path($file)), $report);
        if ($document !== null) {
            $roots = (array) ($document['blocks'] ?? []);
            ['nodes' => $nodes, 'origins' => $origins] = $this->translator->nodes($roots, '/blocks', [], $report);
            $clean = $this->cleaner->clean($nodes, $origins, $report, $user, $context);
        }

        $problems = collect($report->toArray()['entries'])->whereIn('level', ['error', 'warning']);
        if ($document === null || $problems->isNotEmpty() || ! isset($clean)) {
            $first = $problems->first();
            throw new RuntimeException("Starter kit file {$file}: ".($first['message'] ?? 'not valid').(isset($first['path']) ? " ({$first['path']})" : ''));
        }

        $template = (array) ($document['template'] ?? []);

        return [
            'blocks' => $clean,
            'template' => array_filter([
                'name' => $template['name'] ?? null,
                'slug' => $template['slug'] ?? null,
                'description' => $template['description'] ?? null,
                'scope' => $template['scope'] ?? 'section',
                'category' => $template['category'] ?? null,
            ], fn ($value) => $value !== null),
        ];
    }
}
