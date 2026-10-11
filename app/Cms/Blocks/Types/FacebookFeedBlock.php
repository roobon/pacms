<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;
use App\Services\Settings\SettingsService;

/**
 * Posts of the connected Facebook Page (CMS-ARCHITECTURE.md §17.2), read from local storage.
 * With no posts yet the block hides itself, or shows a "Follow us on Facebook" link.
 * The Facebook Page Plugin (an iframe with Meta trackers) is not used.
 */
class FacebookFeedBlock extends BlockType
{
    public function slug(): string
    {
        return 'facebook-feed';
    }

    public function label(): string
    {
        return 'Facebook posts';
    }

    public function category(): string
    {
        return 'external';
    }

    public function icon(): string
    {
        return 'bi-facebook';
    }

    public function fields(): array
    {
        return [
            Field::text('heading')->label('Heading (optional)'),
            Field::checkbox('show_excerpt')->label('Show the post text')->default(true),
            Field::checkbox('show_date')->label('Show date')->default(true),
            Field::select('when_empty', ['follow' => 'Show a "Follow us on Facebook" link', 'hide' => 'Hide the block'])->label('When there are no posts')->default('follow'),
            Field::text('follow_label')->label('Follow link text')->default('Follow us on Facebook')->max(80),
            Field::url('page_url')->label('Page address for the follow link (optional)')->help('Leave empty to use the Facebook address from Design → Header & footer.'),
        ];
    }

    public function sourceModes(): array
    {
        return ['external'];
    }

    public function externalProviders(): array
    {
        return ['facebook'];
    }

    public function displayModes(): array
    {
        return ['grid', 'list', 'carousel'];
    }

    public function defaults(): array
    {
        return [
            'content' => ['heading' => 'On Facebook', 'show_excerpt' => true, 'show_date' => true, 'when_empty' => 'follow', 'follow_label' => 'Follow us on Facebook'],
            'source' => ['mode' => 'external', 'provider' => 'facebook', 'source' => '', 'limit' => 3],
            'display' => ['mode' => 'grid', 'columns' => ['desktop' => 3, 'tablet' => 2, 'mobile' => 1], 'card_style' => 'elevated', 'image_ratio' => '1:1'],
        ];
    }

    public function data(array $content): array
    {
        $social = (array) app(SettingsService::class)->get('navigation', 'social', []);
        $url = (string) ($content['page_url'] ?? '');
        if ($url === '') {
            $url = (string) ($social['facebook'] ?? '');
        }

        return ['follow_url' => preg_match('#^https://#i', $url) ? $url : null];
    }
}
