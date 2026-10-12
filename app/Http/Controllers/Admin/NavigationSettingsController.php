<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Blocks\Types\SocialLinksBlock;
use App\Enums\MediaKind;
use App\Http\Controllers\Controller;
use App\Models\GlobalBlock;
use App\Models\Media;
use App\Services\ActivityLog\ActivityLogger;
use App\Services\Settings\SettingsService;
use App\Services\Starter\StarterKitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Design → Header & footer (Phase 9): the site's default header and footer (global blocks),
 * how the header behaves, the logo, social profiles and the copyright line.
 */
class NavigationSettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function edit(): View
    {
        $navigation = $this->settings->group('navigation');

        return view('admin.settings.navigation', [
            'navigation' => $navigation,
            'headers' => $this->choices('header'),
            'footers' => $this->choices('footer'),
            'logo' => $navigation['logo_media_id'] ? Media::query()->find($navigation['logo_media_id']) : null,
            'logoDark' => $navigation['logo_dark_media_id'] ? Media::query()->find($navigation['logo_dark_media_id']) : null,
            'networks' => SocialLinksBlock::NETWORKS,
        ]);
    }

    public function update(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $image = Rule::exists('media', 'id')->where('kind', MediaKind::Image->value)->where('disk', config('pacms.media.disk'));
        $global = fn (string $kind) => Rule::exists('global_blocks', 'id')->where('kind', $kind)->whereNull('deleted_at');
        $rules = [
            'header_global_block_id' => ['nullable', 'integer', $global('header')],
            'footer_global_block_id' => ['nullable', 'integer', $global('footer')],
            'sticky_header' => ['boolean'],
            'transparent_header' => ['boolean'],
            'logo_media_id' => ['nullable', 'integer', $image],
            'logo_dark_media_id' => ['nullable', 'integer', $image],
            'copyright' => ['nullable', 'string', 'max:255'],
            'social' => ['array'],
        ];
        foreach (array_keys(SocialLinksBlock::NETWORKS) as $network) {
            $rules["social.{$network}"] = ['nullable', 'url:https', 'max:500'];
        }
        $request->merge(['sticky_header' => $request->boolean('sticky_header'), 'transparent_header' => $request->boolean('transparent_header')]);
        $data = $request->validate($rules, [], ['social.*' => 'profile address', 'logo_media_id' => 'logo', 'logo_dark_media_id' => 'logo for dark backgrounds']);

        $this->settings->set('navigation', [
            'header_global_block_id' => isset($data['header_global_block_id']) ? (int) $data['header_global_block_id'] : null,
            'footer_global_block_id' => isset($data['footer_global_block_id']) ? (int) $data['footer_global_block_id'] : null,
            'sticky_header' => (bool) $data['sticky_header'],
            'transparent_header' => (bool) $data['transparent_header'],
            'logo_media_id' => isset($data['logo_media_id']) ? (int) $data['logo_media_id'] : null,
            'logo_dark_media_id' => isset($data['logo_dark_media_id']) ? (int) $data['logo_dark_media_id'] : null,
            'social' => array_filter(array_intersect_key((array) ($data['social'] ?? []), SocialLinksBlock::NETWORKS), fn ($url) => is_string($url) && $url !== ''),
            'copyright' => trim((string) ($data['copyright'] ?? '')),
        ], $request->user());
        $logger->log('settings.updated', null, ['group' => 'navigation'], subjectLabel: 'Header & footer');

        return back()->with('success', __('Header & footer saved.'));
    }

    /**
     * Headers or footers to choose from: the starter kit's in style order (Classic first), then
     * the site's own by name.
     *
     * @return Collection<int, GlobalBlock>
     */
    private function choices(string $kind): Collection
    {
        $order = array_flip(array_values(app(StarterKitService::class)->manifest()['style_globals'][$kind]));

        return GlobalBlock::query()->where('kind', $kind)->orderBy('name')->get(['id', 'name', 'slug', 'description', 'published_revision_id'])
            ->sortBy(fn (GlobalBlock $block) => $order[$block->slug] ?? count($order))
            ->values();
    }
}
