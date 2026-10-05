<?php

namespace App\Http\Requests\Admin;

use App\Enums\MediaKind;
use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Page working-copy fields + SEO. Authorization happens in the controller (PagePolicy);
 * path rules (reserved words, uniqueness, depth) are enforced by PagePathService.
 */
class PageRequest extends FormRequest
{
    /**
     * Authorize before validating, so users without access get 403 rather than validation errors.
     */
    public function authorize(): bool
    {
        $page = $this->route('page');

        return $page instanceof Page
            ? (bool) $this->user()?->can('update', $page)
            : (bool) $this->user()?->can('create', Page::class);
    }

    protected function prepareForValidation(): void
    {
        $slug = trim((string) $this->input('slug'));

        $this->merge([
            'slug' => $slug === '' ? null : Str::lower($slug),
            'seo' => array_merge((array) $this->input('seo', []), [
                'robots_index' => $this->boolean('seo.robots_index', true),
                'robots_follow' => $this->boolean('seo.robots_follow', true),
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $image = Rule::exists('media', 'id')->where('kind', MediaKind::Image->value);

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:191'],
            'parent_id' => ['nullable', 'integer', Rule::exists('pages', 'id')->whereNull('deleted_at')],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'featured_media_id' => ['nullable', 'integer', $image],
            'template' => ['required', Rule::in(array_keys(config('pacms.pages.templates')))],
            'lock_version' => [$this->route('page') ? 'required' : 'nullable', 'integer'],

            'seo' => ['array'],
            'seo.title' => ['nullable', 'string', 'max:255'],
            'seo.description' => ['nullable', 'string', 'max:500'],
            'seo.canonical_url' => ['nullable', 'url:http,https', 'max:2048'],
            'seo.robots_index' => ['boolean'],
            'seo.robots_follow' => ['boolean'],
            'seo.og_title' => ['nullable', 'string', 'max:255'],
            'seo.og_description' => ['nullable', 'string', 'max:500'],
            'seo.og_image_media_id' => ['nullable', 'integer', $image],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'seo.title' => 'SEO title',
            'seo.description' => 'meta description',
            'seo.canonical_url' => 'canonical URL',
            'seo.og_image_media_id' => 'social image',
            'featured_media_id' => 'featured image',
            'parent_id' => 'parent page',
        ];
    }
}
