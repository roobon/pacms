<?php

namespace App\Services\Navigation;

use App\Cms\Blocks\BlockPayloadResolver;
use App\Cms\Blocks\BlockTreeRepository;
use App\Cms\Blocks\BlockTreeValidator;
use App\Cms\Content\ContentType;
use App\Cms\Content\ContentTypeRegistry;
use App\Models\ContentItem;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Term;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogger;
use App\Services\Cache\CacheVersions;
use App\Services\Content\ContentReferenceService;
use App\Services\Settings\SettingsService;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Menus (CMS-ARCHITECTURE.md §13.2): saving the tree from the Menu Builder, and resolving it
 * for the website.
 *
 * - Links to pages, content items and categories are stored by id and turned into URLs when
 *   rendered; items whose target is not public are left out (with their sub-items).
 * - Every item keeps its visibility (everyone, guests, members); the website hides items for
 *   the visitor's state, so one cached menu serves everyone (the links are not secret).
 * - Item ids stay stable across saves (mega panels hang off them).
 */
class MenuService
{
    public const MAX_ITEMS = 300;

    public function __construct(
        private readonly ContentTypeRegistry $types,
        private readonly CacheVersions $versions,
        private readonly ActivityLogger $logger,
        private readonly SettingsService $settings,
        private readonly BlockTreeRepository $tree,
        private readonly BlockTreeValidator $validator,
        private readonly ContentReferenceService $references,
        private readonly BlockPayloadResolver $blocks,
    ) {}

    /**
     * @param  array<string, mixed>  $data  name, slug?
     */
    public function create(User $user, array $data): Menu
    {
        $menu = new Menu(['name' => trim((string) $data['name'])]);
        $menu->slug = $this->uniqueSlug((string) ($data['slug'] ?? '') ?: (string) $data['name']);
        $menu->forceFill(['created_by' => $user->id, 'updated_by' => $user->id])->save();
        $this->logger->log('menu.created', $menu, [], $user, $menu->name);
        $this->changed();

        return $menu;
    }

    /**
     * @param  array<string, mixed>  $data  name
     */
    public function rename(User $user, Menu $menu, array $data): Menu
    {
        $menu->forceFill(['name' => trim((string) $data['name']), 'updated_by' => $user->id])->save();
        $this->logger->log('menu.updated', $menu, [], $user, $menu->name);
        $this->changed();

        return $menu;
    }

    public function delete(User $user, Menu $menu): void
    {
        $menu->delete();
        $this->logger->log('menu.deleted', $menu, [], $user, $menu->name);
        $this->changed();
    }

    /**
     * Replace the menu's items with the tree from the Menu Builder.
     *
     * @param  list<array<string, mixed>>  $tree
     *
     * @throws ValidationException
     */
    public function saveTree(User $user, Menu $menu, array $tree, int $lockVersion): Menu
    {
        if ($menu->lock_version !== $lockVersion) {
            throw ValidationException::withMessages(['menu' => __('Someone else changed this menu in the meantime. Reload the page to see their changes.')]);
        }

        $rows = [];
        $errors = [];
        $this->flatten($tree, null, 1, $rows, $errors);
        if (count($rows) > self::MAX_ITEMS) {
            $errors['items'] = __('A menu can have at most :max items.', ['max' => self::MAX_ITEMS]);
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($user, $menu, $rows) {
            $existing = MenuItem::query()->where('menu_id', $menu->id)->pluck('id')->all();
            $kept = [];
            $ids = []; // client key => saved id
            foreach ($rows as $row) {
                $attributes = $row['attributes'] + [
                    'menu_id' => $menu->id,
                    'parent_id' => $row['parent'] === null ? null : $ids[$row['parent']],
                    'position' => $row['position'],
                ];
                $item = in_array($row['id'], $existing, true) ? MenuItem::query()->find($row['id']) : new MenuItem;
                // A mega panel stays with its item while the item stays at the top level.
                $attributes['is_mega'] = $item->is_mega && $attributes['parent_id'] === null;
                if ($item->is_mega && ! $attributes['is_mega']) {
                    $this->tree->save($item, []);
                }
                $item->forceFill($attributes)->save();
                $ids[$row['key']] = $item->id;
                $kept[] = $item->id;
            }
            // Children first, so no foreign key points at a removed parent; panels go with their item.
            foreach (MenuItem::query()->where('menu_id', $menu->id)->whereNotIn('id', $kept)->orderByDesc('id')->get() as $removed) {
                if ($removed->is_mega) {
                    $this->tree->save($removed, []);
                    $this->references->clear($removed);
                }
                $removed->delete();
            }

            $menu->forceFill(['lock_version' => $menu->lock_version + 1, 'updated_by' => $user->id])->save();
            $this->logger->log('menu.items_saved', $menu, ['items' => count($rows)], $user, $menu->name);
        });
        $this->changed();

        return $menu->fresh() ?? $menu;
    }

    /**
     * Save the mega panel of a top-level item (Phase 9B): a block tree shown when the item
     * opens, instead of a list of its sub-items. Menus are not staged, so it is live at once.
     *
     * @param  list<array<string, mixed>>  $nodes
     *
     * @throws ValidationException
     */
    public function savePanel(User $user, MenuItem $item, array $nodes): void
    {
        if ($item->parent_id !== null) {
            throw ValidationException::withMessages(['blocks' => __('Only top-level items can open a mega panel.')]);
        }
        $clean = $this->validator->validate($nodes, $user, BlockTreeValidator::CONTEXT_GLOBAL);

        DB::transaction(function () use ($user, $item, $clean) {
            $this->tree->save($item, $clean, $user);
            $this->references->sync($item, $this->tree->references($clean));
            $item->forceFill(['is_mega' => true])->save();
            $item->menu()->first()?->forceFill(['updated_by' => $user->id])->save();
        });
        $this->logger->log('menu.panel_saved', $item->menu()->first(), ['item' => $item->id], $user);
        $this->changed();
    }

    public function removePanel(User $user, MenuItem $item): void
    {
        DB::transaction(function () use ($item) {
            $this->tree->save($item, []);
            $this->references->clear($item);
            $item->forceFill(['is_mega' => false])->save();
        });
        $this->logger->log('menu.panel_removed', $item->menu()->first(), ['item' => $item->id], $user);
        $this->changed();
    }

    /**
     * The label an item shows: its own, or its target's title.
     */
    public function labelOf(MenuItem $item): string
    {
        if ($item->label) {
            return $item->label;
        }
        $target = $item->linkable_type ? ($this->targets(collect([$item]))[$item->linkable_type.':'.$item->linkable_id] ?? null) : null;

        return $target['title'] ?? __('Menu item');
    }

    /**
     * Menus "main" and "footer" for a new site (starter kit): created when missing and, when
     * empty, filled with the home page and the published top-level pages. Menus with
     * items are left alone.
     *
     * @return list<array{0: string, 1: string, 2: string}> [kind, name, result] rows
     */
    public function prefill(User $user): array
    {
        $home = (int) $this->settings->get('site', 'homepage_page_id');
        $pages = Page::query()->live()->whereNull('parent_id')->orderByRaw('id = ? desc', [$home])->orderBy('published_path')->limit(7)->get(['id']);
        $items = $pages->map(fn (Page $page) => ['type' => 'page', 'target' => ['id' => $page->id]])->all();

        $done = [];
        foreach (['main' => 'Main', 'footer' => 'Footer'] as $slug => $name) {
            $menu = Menu::query()->where('slug', $slug)->first() ?? $this->create($user, ['name' => $name, 'slug' => $slug]);
            if (MenuItem::query()->where('menu_id', $menu->id)->exists()) {
                $done[] = ['Menu', $menu->name, 'already has items'];

                continue;
            }
            $this->saveTree($user, $menu, $items, $menu->lock_version);
            $done[] = ['Menu', $menu->name, $items === [] ? 'created (empty: no published pages yet)' : 'created with '.count($items).' pages'];
        }

        return $done;
    }

    /**
     * The menu as the website shows it (cached), or [] for an unknown menu.
     *
     * @return list<array<string, mixed>>
     */
    public function resolve(string $slug): array
    {
        $key = 'pacms:menu:'.sha1($slug).':'.$this->versions->fingerprint('menus', 'pages', 'settings', 'media', 'globals', 'block_types', 'testimonials', ...array_keys($this->types->all()));

        return Cache::remember($key, now()->addDay(), function () use ($slug) {
            $menu = Menu::query()->where('slug', $slug)->first();

            return $menu === null ? [] : $this->tree($menu, admin: false);
        });
    }

    /**
     * The menu for the Menu Builder: every item, with its target's title, address and
     * whether it is public.
     *
     * @return list<array<string, mixed>>
     */
    public function forBuilder(Menu $menu): array
    {
        return $this->tree($menu, admin: true);
    }

    /**
     * Choices for Menu blocks: slug => name.
     *
     * @return array<string, string>
     */
    public function choices(): array
    {
        try {
            return Menu::query()->orderBy('name')->pluck('name', 'slug')->all();
        } catch (QueryException) {
            return []; // before the Phase 9 migration
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tree(Menu $menu, bool $admin): array
    {
        $items = MenuItem::query()->where('menu_id', $menu->id)->orderBy('position')->orderBy('id')->get();
        $targets = $this->targets($items);

        $build = function (?int $parent) use (&$build, $items, $targets, $admin): array {
            $out = [];
            foreach ($items->where('parent_id', $parent) as $item) {
                $target = $item->linkable_type ? ($targets[$item->linkable_type.':'.$item->linkable_id] ?? null) : null;
                $children = $build($item->id);
                $url = match ($item->type) {
                    'page', 'content', 'term' => $target['url'] ?? null,
                    'external_url', 'custom_url' => $item->url,
                    default => null,
                };
                $public = match ($item->type) {
                    'page', 'content', 'term' => $target !== null && $target['public'],
                    'group' => $children !== [] || $item->is_mega || $admin,
                    default => true,
                };
                if (! $admin && ! $public) {
                    continue; // and its sub-items
                }

                $node = [
                    'id' => $item->id,
                    // Top-level items with a mega panel: its blocks (shown instead of the sub-items list).
                    'panel' => $item->is_mega && $item->parent_id === null && ! $admin ? $this->blocks->resolve($this->tree->load($item)) : null,
                    'label' => $item->label ?: ($target['title'] ?? ''),
                    'url' => $url,
                    'external' => $item->type === 'external_url',
                    'new_tab' => $item->open_in_new_tab,
                    'icon' => $item->icon,
                    'class' => $item->css_class,
                    'visibility' => $item->visibility,
                    'children' => $children,
                ];
                if ($admin) {
                    $node += [
                        'type' => $item->type,
                        'own_label' => $item->label,
                        'target' => $item->linkable_type === null ? null : array_filter([
                            'entity' => $target['entity'] ?? null,
                            'id' => $item->linkable_id,
                            'title' => $target['title'] ?? null,
                            'path' => $url,
                        ], fn ($value) => $value !== null),
                        'public' => $public,
                        'is_mega' => $item->is_mega,
                        'panel_url' => $item->parent_id === null ? route('admin.menus.panel.edit', ['menu' => $item->menu_id, 'item' => $item->id]) : null,
                    ];
                }
                $out[] = $node;
            }

            return $out;
        };

        return $build(null);
    }

    /**
     * Every linked target at once: "morph:id" => [title, url, public, entity].
     *
     * @param  Collection<int, MenuItem>  $items
     * @return array<string, array{title: string, url: string|null, public: bool, entity: string}>
     */
    private function targets(Collection $items): array
    {
        $targets = [];
        foreach ($items->whereNotNull('linkable_type')->groupBy('linkable_type') as $morph => $group) {
            $ids = $group->pluck('linkable_id')->unique()->all();
            if ($morph === 'page') {
                $home = (int) $this->settings->get('site', 'homepage_page_id');
                foreach (Page::query()->whereKey($ids)->get() as $page) {
                    $url = $page->id === $home ? '/' : '/'.($page->isLive() ? $page->published_path : $page->path);
                    $targets["page:{$page->id}"] = ['title' => $page->title, 'url' => $url, 'public' => $page->isLive(), 'entity' => 'pages'];
                }
            } elseif ($morph === 'term') {
                foreach (Term::query()->whereKey($ids)->get() as $term) {
                    $type = $this->typeForTaxonomy($term->taxonomy);
                    $targets["term:{$term->id}"] = [
                        'title' => $term->name,
                        'url' => $type === null ? null : '/'.$type->routePrefix().'?category='.$term->slug,
                        'public' => $type !== null,
                        'entity' => 'terms',
                    ];
                }
            } else {
                $class = Relation::getMorphedModel((string) $morph);
                if ($class === null || ! is_a($class, ContentItem::class, true)) {
                    continue;
                }
                foreach ($class::query()->whereKey($ids)->get() as $item) {
                    $type = $this->types->find($item->contentTypeKey());
                    if ($type === null) {
                        continue; // a disabled admin-made type
                    }
                    $public = $type->hasDetailPages() && $type->query()->published()->whereKey($item->getKey())->exists();
                    $targets["{$morph}:{$item->getKey()}"] = ['title' => (string) $item->getAttribute('title'), 'url' => $item->url(), 'public' => $public, 'entity' => $type->key()];
                }
            }
        }

        return $targets;
    }

    /**
     * The module whose category list a taxonomy is, if it has a listing page.
     */
    private function typeForTaxonomy(string $taxonomy): ?ContentType
    {
        foreach ($this->types->all() as $type) {
            if ($type->taxonomy() === $taxonomy && $type->hasArchive()) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Validate the builder tree into rows in save order (parents before children).
     *
     * @param  list<mixed>  $nodes
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, array<array-key, mixed>|string>  $errors
     */
    private function flatten(array $nodes, ?string $parent, int $depth, array &$rows, array &$errors): void
    {
        foreach (array_values($nodes) as $position => $node) {
            $key = 'n'.count($rows);
            $path = "items.{$key}";
            if (! is_array($node)) {
                $errors[$path] = __('Not a menu item.');

                continue;
            }
            if ($depth > Menu::MAX_DEPTH) {
                $errors[$path] = __('Menus can be at most :max levels deep.', ['max' => Menu::MAX_DEPTH]);

                return;
            }

            $attributes = $this->attributes($node, $path, $errors);
            $rows[] = ['key' => $key, 'id' => is_int($node['id'] ?? null) ? $node['id'] : null, 'parent' => $parent, 'position' => $position, 'attributes' => $attributes];
            $this->flatten(is_array($node['children'] ?? null) ? $node['children'] : [], $key, $depth + 1, $rows, $errors);
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, array<array-key, mixed>|string>  $errors
     * @return array<string, mixed>
     */
    private function attributes(array $node, string $path, array &$errors): array
    {
        $type = (string) ($node['type'] ?? '');
        $label = mb_substr(HtmlSanitizer::plain((string) ($node['label'] ?? '')), 0, 191);
        $attributes = [
            'type' => $type,
            'label' => $label === '' ? null : $label,
            'linkable_type' => null,
            'linkable_id' => null,
            'url' => null,
            'open_in_new_tab' => ! empty($node['new_tab']),
            'icon' => preg_match('/^bi-[a-z0-9-]{1,60}$/', (string) ($node['icon'] ?? '')) ? $node['icon'] : null,
            'css_class' => null,
            'visibility' => in_array($node['visibility'] ?? 'everyone', array_keys(MenuItem::VISIBILITY), true) ? $node['visibility'] ?? 'everyone' : 'everyone',
            'is_mega' => false,
        ];

        $class = trim((string) ($node['class'] ?? ''));
        if ($class !== '') {
            if (! preg_match('/^[A-Za-z_-][A-Za-z0-9_-]*( [A-Za-z_-][A-Za-z0-9_-]*)*$/', $class) || strlen($class) > 191) {
                $errors["{$path}.class"] = __('CSS classes: letters, numbers, hyphens and underscores, separated by spaces.');
            }
            $attributes['css_class'] = $class;
        }

        $target = is_array($node['target'] ?? null) ? $node['target'] : [];
        $id = (int) ($target['id'] ?? 0);
        switch ($type) {
            case 'page':
                if (! Page::query()->whereKey($id)->exists()) {
                    $errors["{$path}.target"] = __('Choose a page.');
                }
                $attributes['linkable_type'] = 'page';
                $attributes['linkable_id'] = $id;
                break;
            case 'content':
                $contentType = $this->types->find((string) ($target['entity'] ?? ''));
                $item = $contentType?->query()->whereKey($id)->first();
                if ($item === null) {
                    $errors["{$path}.target"] = __('Choose an item.');
                }
                $attributes['linkable_type'] = $item?->getMorphClass();
                $attributes['linkable_id'] = $id;
                break;
            case 'term':
                $term = Term::query()->find($id);
                if ($term === null || $this->typeForTaxonomy($term->taxonomy) === null) {
                    $errors["{$path}.target"] = __('Choose a category of a module with a listing page.');
                }
                $attributes['linkable_type'] = 'term';
                $attributes['linkable_id'] = $id;
                break;
            case 'external_url':
                $url = trim((string) ($node['url'] ?? ''));
                if (! preg_match('#^(https?://[^\s<>"]+|mailto:[^\s<>"]+|tel:[0-9+().\s-]+)$#i', $url) || strlen($url) > 2048) {
                    $errors["{$path}.url"] = __('Use a full address starting with https://, or mailto: or tel:.');
                }
                $attributes['url'] = $url;
                break;
            case 'custom_url':
                $url = trim((string) ($node['url'] ?? ''));
                if (! preg_match('#^(/(?!/)[^\s<>"]*|\#[A-Za-z0-9_-]+)$#', $url) || strlen($url) > 2048) {
                    $errors["{$path}.url"] = __('Use an address on this site starting with /, e.g. /donate, or an anchor like #contact.');
                }
                $attributes['url'] = $url;
                break;
            case 'group':
                if ($label === '') {
                    $errors["{$path}.label"] = __('A heading needs a label.');
                }
                break;
            default:
                $errors["{$path}.type"] = __('Choose what the item links to.');
        }
        if (in_array($type, ['external_url', 'custom_url'], true) && $label === '') {
            $errors["{$path}.label"] = __('Give the link a label.');
        }

        return $attributes;
    }

    private function uniqueSlug(string $wanted): string
    {
        $base = Str::limit(Str::slug($wanted) ?: 'menu', 80, '');
        $slug = $base;
        $n = 2;
        while (Menu::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }

    private function changed(): void
    {
        // Headers, footers and pages that show menus.
        $this->versions->bump('menus', 'globals', 'pages');
    }
}
