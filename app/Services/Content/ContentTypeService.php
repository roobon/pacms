<?php

namespace App\Services\Content;

use App\Auth\PermissionCatalog;
use App\Cms\Blocks\BlockRegistry;
use App\Cms\Blocks\Types\ContentTypeBlock;
use App\Cms\Content\ContentTypeRegistry;
use App\Cms\Content\Types\AdminContentType;
use App\Cms\Fields\FieldDefinitionValidator;
use App\Models\BlockType;
use App\Models\ContentReference;
use App\Models\CustomContentType;
use App\Models\CustomItem;
use App\Models\Page;
use App\Models\Term;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogger;
use App\Services\Cache\CacheVersions;
use App\Services\Seo\RedirectService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creating, changing, disabling and deleting content types in the admin (Phase 8D).
 *
 * - The key (permissions, registry) is made from the plural name and never changes.
 * - The URL prefix must be free: not reserved, not another type's, not a top-level page.
 * - Fields are validated by the field builder's validator, limited to the types a content
 *   type supports; values of a removed field stay stored (shown again if it is re-added).
 * - Permissions are created with the type and given to roles like a built-in module's.
 * - Each type has a collection block ("type/{key}"); a type whose block is on a page,
 *   global block or template cannot be deleted (D-1 of 8D.2).
 */
class ContentTypeService
{
    /** Roles that receive a new type's permissions, like a built-in module (D-07). */
    private const AUTHORING = ['view', 'create', 'update_own', 'submit'];

    public function __construct(
        private readonly FieldDefinitionValidator $fieldValidator,
        private readonly ContentTypeRegistry $registry,
        private readonly ActivityLogger $logger,
        private readonly CacheVersions $cache,
        private readonly BlockRegistry $blocks,
        private readonly ContentReferenceService $references,
        private readonly RedirectService $redirects,
    ) {}

    /**
     * @param  array<string, mixed>  $data  label, singular, icon, route_prefix, workflow, options, fields, display
     */
    public function create(User $user, array $data): CustomContentType
    {
        $key = $this->newKey((string) $data['label']);
        $prefix = $this->prefix((string) ($data['route_prefix'] ?? '') ?: (string) $data['label'], null);
        $fields = $this->fields($data['fields'] ?? []);

        $type = DB::transaction(function () use ($user, $data, $key, $prefix, $fields) {
            $type = new CustomContentType;
            $type->fill($this->attributes($data, $fields) + ['route_prefix' => $prefix]);
            $type->forceFill(['key' => $key, 'workflow' => ($data['workflow'] ?? 'editorial') === 'managed' ? 'managed' : 'editorial', 'created_by' => $user->id, 'updated_by' => $user->id]);
            $type->save();

            $this->createPermissions($type);
            $this->logger->log('content_type.created', $type, ['key' => $key], $user, $type->label);

            return $type;
        });

        $this->changed();

        return $type;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, CustomContentType $type, array $data): CustomContentType
    {
        $prefix = $this->prefix((string) ($data['route_prefix'] ?? $type->route_prefix), $type);
        $fields = $this->fields($data['fields'] ?? []);

        DB::transaction(function () use ($user, $type, $data, $prefix, $fields) {
            $old = $type->route_prefix;
            $type->fill($this->attributes($data, $fields) + ['route_prefix' => $prefix]);
            if ($prefix !== $old) {
                // The old address keeps working (301) and stays reserved; a re-used one is freed.
                $type->former_prefixes = array_values(array_diff(array_unique([...($type->former_prefixes ?? []), $old]), [$prefix]));
            }
            $type->forceFill(['updated_by' => $user->id])->save();
            if ($prefix !== $old) {
                $this->redirectAddress($type, $old, $prefix, $user);
            }
            $this->logger->log('content_type.updated', $type, ['changed' => array_keys($type->getChanges())], $user, $type->label);
        });

        $this->changed();

        return $type;
    }

    /**
     * Delete a type that has no items left (deleted items in the bin are removed for good).
     *
     * @throws ValidationException
     */
    public function delete(User $user, CustomContentType $type): void
    {
        $items = CustomItem::query()->where('content_type_id', $type->id)->count();
        if ($items > 0) {
            throw ValidationException::withMessages(['type' => trans_choice(
                '":label" still has :count item. Delete it first, or disable the type instead.|":label" still has :count items. Delete them first, or disable the type instead.',
                $items,
                ['label' => $type->label, 'count' => $items],
            )]);
        }

        $block = BlockType::query()->where('slug', ContentTypeBlock::slugFor($type->key))->first();
        $usages = $block === null ? collect() : $this->references->usagesOf($block);
        if ($usages->isNotEmpty()) {
            $places = $usages->map(fn (ContentReference $reference) => $this->placeName($reference))->unique()->take(5)->implode(', ');
            throw ValidationException::withMessages(['type' => trans_choice(
                'The ":label" block is used in :count place (:places). Remove it there first, or disable the type instead.|The ":label" block is used in :count places (:places). Remove it there first, or disable the type instead.',
                $usages->count(),
                ['label' => $type->label, 'count' => $usages->count(), 'places' => $places],
            )]);
        }

        DB::transaction(function () use ($user, $type, $block) {
            $block?->delete();
            // Its categories go too (children first: a parent cannot be removed before them).
            $taxonomy = AdminContentType::taxonomyFor($type->key);
            Term::withTrashed()->where('taxonomy', $taxonomy)->update(['parent_id' => null]);
            Term::withTrashed()->where('taxonomy', $taxonomy)->forceDelete();
            CustomItem::withTrashed()->where('content_type_id', $type->id)->forceDelete();
            Permission::query()->whereIn('name', PermissionCatalog::forAdminMadeType($type->key, $type->workflow))->delete();
            $type->delete();
            $this->logger->log('content_type.deleted', $type, ['key' => $type->key], $user, $type->label);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->changed();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private function attributes(array $data, array $fields): array
    {
        $keys = array_column($fields, 'key');
        $display = [];
        foreach ((array) ($data['display'] ?? []) as $key => $value) {
            if (in_array($key, $keys, true) && isset(AdminContentType::DISPLAYS[$value])) {
                $display[$key] = $value;
            }
        }

        return [
            'label' => trim((string) $data['label']),
            'singular' => trim((string) ($data['singular'] ?? '')) ?: Str::singular(trim((string) $data['label'])),
            'icon' => preg_match('/^bi-[a-z0-9-]{1,60}$/', (string) ($data['icon'] ?? '')) ? $data['icon'] : 'bi-collection',
            'fields' => $fields,
            'display' => $display,
            'has_archive' => (bool) ($data['has_archive'] ?? true),
            'searchable' => (bool) ($data['searchable'] ?? true),
            'has_categories' => (bool) ($data['has_categories'] ?? false),
            'has_documents' => (bool) ($data['has_documents'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /**
     * Field definitions from the field builder, validated and limited to content-type types.
     *
     * @return list<array<string, mixed>>
     *
     * @throws ValidationException
     */
    private function fields(mixed $input): array
    {
        $fields = $this->fieldValidator->validate($input);
        $errors = [];
        foreach ($fields as $i => $field) {
            if (! in_array($field['type'], AdminContentType::FIELD_TYPES, true)) {
                $errors["fields.{$i}.type"] = __('This type of field is not available for content types.');
            }
            if (in_array($field['key'], AdminContentType::RESERVED_KEYS, true)) {
                $errors["fields.{$i}.key"] = __('":key" is used by every item already. Choose another key.', ['key' => $field['key']]);
            }
            foreach ((array) ($field['fields'] ?? []) as $j => $sub) {
                if (! in_array($sub['type'], AdminContentType::ROW_TYPES, true)) {
                    $errors["fields.{$i}.fields.{$j}.type"] = __('Rows can hold text, long text, web addresses, e-mail addresses, numbers and dates.');
                }
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $fields;
    }

    private function newKey(string $label): string
    {
        $base = Str::limit(Str::slug($label, '_'), 50, '') ?: 'items';
        if (! preg_match('/^[a-z]/', $base)) {
            $base = 'type_'.$base;
        }
        $key = $base;
        $n = 2;
        while ($this->keyTaken($key)) {
            $key = "{$base}_{$n}";
            $n++;
        }

        return $key;
    }

    private function keyTaken(string $key): bool
    {
        return CustomContentType::query()->where('key', $key)->exists()
            || in_array($key, array_map(fn ($class) => app($class)->key(), (array) config('pacms.content_types')), true)
            // Permission prefixes that already mean something else.
            || in_array($key, ['admin', 'dashboard', 'pages', 'media', 'users', 'settings', 'testimonials', 'team', 'partners', 'blocks', 'templates', 'global_blocks', 'content_types', 'revisions', 'import', 'export', 'seo', 'redirects', 'taxonomies', 'menus', 'activity_log', 'backups', 'external_sources', 'facebook', 'block_types', 'design_tokens'], true);
    }

    /**
     * A free URL prefix, or a validation error naming what uses it.
     *
     * @throws ValidationException
     */
    private function prefix(string $wanted, ?CustomContentType $current): string
    {
        $prefix = Str::slug($wanted);
        $fail = fn (string $message) => throw ValidationException::withMessages(['route_prefix' => $message]);

        if ($prefix === '' || strlen($prefix) > 64) {
            $fail(__('Use lowercase letters, numbers and hyphens (at most 64).'));
        }
        if ($current === null || $prefix !== $current->route_prefix) {
            if (in_array($prefix, (array) config('pacms.pages.reserved_slugs'), true)) {
                $fail(__('/:prefix is used by the system. Choose another URL.', ['prefix' => $prefix]));
            }
            foreach ($this->registry->all() as $type) {
                if ($type->routePrefix() === $prefix) {
                    $fail(__('/:prefix is already used by :label.', ['prefix' => $prefix, 'label' => $type->label()]));
                }
            }
            if (CustomContentType::query()->where('route_prefix', $prefix)->when($current, fn ($q) => $q->whereKeyNot($current->id))->exists()) {
                $fail(__('/:prefix is already used by a disabled content type.', ['prefix' => $prefix]));
            }
            if (CustomContentType::query()->whereJsonContains('former_prefixes', $prefix)->when($current, fn ($q) => $q->whereKeyNot($current->id))->exists()) {
                $fail(__('/:prefix was the address of another content type and still redirects to it.', ['prefix' => $prefix]));
            }
            if (Page::query()->whereNull('parent_id')->where('slug', $prefix)->exists()) {
                $fail(__('/:prefix is already the address of a page. Choose another URL or change the page.', ['prefix' => $prefix]));
            }
        }

        return $prefix;
    }

    /**
     * The type's permissions, given to roles like a built-in module's: everything for
     * administrators and editors, writing and submitting for authors and contributors.
     */
    private function createPermissions(CustomContentType $type): void
    {
        $permissions = PermissionCatalog::forAdminMadeType($type->key, $type->workflow);
        foreach ($permissions as $name) {
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $authoring = $type->workflow === 'managed' ? [] : array_map(fn (string $action) => "{$type->key}.{$action}", self::AUTHORING);
        foreach ([PermissionCatalog::SUPER_ADMIN => $permissions, 'administrator' => $permissions, 'editor' => $permissions, 'author' => $authoring, 'contributor' => $authoring] as $role => $grant) {
            $model = Role::query()->where(['name' => $role, 'guard_name' => 'web'])->first();
            if ($model !== null && $grant !== []) {
                $model->givePermissionTo($grant);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * 301s from the old address: the listing page and every item's page.
     */
    private function redirectAddress(CustomContentType $type, string $from, string $to, User $user): void
    {
        $this->redirects->recordMove('/'.$from, '/'.$to, $user);
        CustomItem::query()->where('content_type_id', $type->id)->select(['id', 'slug'])->chunkById(500, function ($items) use ($from, $to, $user) {
            foreach ($items as $item) {
                $this->redirects->recordMove("/{$from}/{$item->slug}", "/{$to}/{$item->slug}", $user);
            }
        });
    }

    /**
     * "Page: About us", "Global block: Footer"… for the delete message.
     */
    private function placeName(ContentReference $reference): string
    {
        $owner = $reference->owner;
        $name = (string) ($owner->title ?? $owner->name ?? '#'.$owner?->getKey());

        return match ($reference->owner_type) {
            'page' => __('page ":name"', ['name' => $name]),
            'global_block' => __('global block ":name"', ['name' => $name]),
            'block_template' => __('template ":name"', ['name' => $name]),
            default => '"'.$name.'"',
        };
    }

    private function changed(): void
    {
        $this->registry->reload();
        // Each type's collection block ("type/{key}") must exist before a page can store it.
        $this->blocks->syncContentTypes();
        // Menus, archives, sitemaps and blocks list content types.
        $this->cache->bump('pages', 'settings', 'block_types');
    }
}
