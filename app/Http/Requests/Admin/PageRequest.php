<?php

namespace App\Http\Requests\Admin;

use App\Enums\MediaKind;
use App\Models\Page;
use App\Rules\SummaryText;
use App\Support\Html\HtmlSanitizer;
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
            'show_title' => $this->boolean('show_title', true),
            // Summary: small editor output, cleaned to paragraphs, bold, italic and links.
            'excerpt' => is_string($this->input('excerpt')) ? (app(HtmlSanitizer::class)->inline($this->input('excerpt')) ?: null) : $this->input('excerpt'),
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
            'excerpt' => ['nullable', 'string', 'max:20000', new SummaryText],
            'featured_media_id' => ['nullable', 'integer', $image],
            'template' => ['required', Rule::in(array_keys(config('pacms.pages.templates')))],
            'show_title' => ['boolean'],
            'header_mode' => ['nullable', Rule::in(array_keys(Page::CHROME_MODES))],
            'header_global_block_id' => ['nullable', 'required_if:header_mode,custom', 'integer', Rule::exists('global_blocks', 'id')->where('kind', 'header')->whereNull('deleted_at')],
            'footer_mode' => ['nullable', Rule::in(array_keys(Page::CHROME_MODES))],
            'footer_global_block_id' => ['nullable', 'required_if:footer_mode,custom', 'integer', Rule::exists('global_blocks', 'id')->where('kind', 'footer')->whereNull('deleted_at')],
            'lock_version' => [$this->route('page') ? 'required' : 'nullable', 'integer'],
            // Block tree from the builder island, as JSON (validated in depth by BlockTreeValidator).
            'blocks' => ['nullable', 'string', 'max:'.((int) config('pacms.blocks.max_payload_kb') * 1024), 'json'],
            // New pages only: start as a copy of a page template (starter kit or saved).
            'start_template_id' => [$this->route('page') ? 'prohibited' : 'nullable', 'integer',
                Rule::exists('block_templates', 'id')->where('scope', 'page')->where('status', 'published')->whereNull('deleted_at')],

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
     * Validated page data for PageService, with the block tree decoded (when sent).
     *
     * @return array<string, mixed>
     */
    public function pageData(): array
    {
        $data = $this->safe()->except(['lock_version', 'blocks', 'start_template_id']);
        // A chosen header or footer only counts with "Choose one".
        foreach (['header', 'footer'] as $role) {
            if (array_key_exists("{$role}_mode", $data)) {
                $data["{$role}_mode"] ??= 'default';
                if ($data["{$role}_mode"] !== 'custom') {
                    $data["{$role}_global_block_id"] = null;
                }
            }
        }

        if ($this->filled('blocks')) {
            $data['blocks'] = json_decode((string) $this->input('blocks'), true, 64) ?? [];
        }

        return $data;
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
            'start_template_id' => 'page template',
            'header_global_block_id' => 'header',
            'footer_global_block_id' => 'footer',
        ];
    }
}
