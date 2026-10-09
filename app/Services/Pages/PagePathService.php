<?php

namespace App\Services\Pages;

use App\Cms\Content\ContentTypeRegistry;
use App\Models\Page;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Slugs, hierarchical working paths and their validation for pages.
 */
class PagePathService
{
    public function slugify(string $value): string
    {
        return Str::limit(Str::slug($value), 191, '');
    }

    public function pathFor(?Page $parent, string $slug): string
    {
        return $parent ? $parent->path.'/'.$slug : $slug;
    }

    /**
     * @throws ValidationException
     */
    public function validate(Page $page, ?Page $parent, string $slug): void
    {
        if ($slug === '' || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw ValidationException::withMessages(['slug' => __('Use lowercase letters, numbers and single hyphens only.')]);
        }

        if ($parent === null && in_array($slug, config('pacms.pages.reserved_slugs'), true)) {
            throw ValidationException::withMessages(['slug' => __('":slug" is reserved by the system. Choose another URL.', ['slug' => $slug])]);
        }

        // Content types made in the admin own their URL prefix too.
        if ($parent === null && ($type = app(ContentTypeRegistry::class)->forRoutePrefix($slug)) !== null) {
            throw ValidationException::withMessages(['slug' => __('/:slug is the address of :label. Choose another URL.', ['slug' => $slug, 'label' => $type->label()])]);
        }

        if ($parent !== null && $page->exists) {
            if ($parent->is($page) || str_starts_with($parent->path.'/', $page->path.'/')) {
                throw ValidationException::withMessages(['parent_id' => __('A page cannot be placed under itself or one of its sub-pages.')]);
            }
        }

        $path = $this->pathFor($parent, $slug);
        $depth = substr_count($path, '/') + 1 + $this->subtreeDepth($page);
        if ($depth > config('pacms.pages.max_depth')) {
            throw ValidationException::withMessages(['parent_id' => __('Pages can be nested at most :max levels deep.', ['max' => config('pacms.pages.max_depth')])]);
        }

        $taken = Page::query()
            ->where('path', $path)
            ->when($page->exists, fn ($query) => $query->whereKeyNot($page->getKey()))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['slug' => __('Another page already uses the URL /:path.', ['path' => $path])]);
        }
    }

    /**
     * Recompute the working paths of all descendants after a page's path changed.
     */
    public function rebuildDescendants(Page $page): void
    {
        foreach ($page->children()->get() as $child) {
            $child->path = $page->path.'/'.$child->slug;
            $child->saveQuietly();
            $this->rebuildDescendants($child);
        }
    }

    /**
     * Levels below this page (0 when it has no children).
     */
    private function subtreeDepth(Page $page): int
    {
        if (! $page->exists) {
            return 0;
        }

        $deepest = Page::query()
            ->where('path', 'like', $page->path.'/%')
            ->pluck('path')
            ->map(fn (string $path) => substr_count($path, '/'))
            ->max();

        return $deepest === null ? 0 : $deepest - substr_count((string) $page->path, '/');
    }
}
