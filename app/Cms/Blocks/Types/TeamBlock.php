<?php

namespace App\Cms\Blocks\Types;

/**
 * Team collection: people in their display order (a department, or featured people only,
 * through the source filters), shown as person cards.
 */
class TeamBlock extends ContentCollectionBlock
{
    protected function entity(): string
    {
        return 'team';
    }

    protected function emptyText(): string
    {
        return 'No team members yet.';
    }

    protected function sourceDefaults(): array
    {
        return ['order' => 'position', 'limit' => 12];
    }

    public function label(): string
    {
        return 'Team';
    }

    public function icon(): string
    {
        return 'bi-people';
    }

    public function defaults(): array
    {
        $defaults = parent::defaults();
        $defaults['content']['heading'] = 'Our team';
        $defaults['content']['show_date'] = false;
        $defaults['display'] = ['mode' => 'grid', 'columns' => ['desktop' => 4, 'tablet' => 3, 'mobile' => 2], 'card_style' => 'flat', 'image_ratio' => '1:1'];

        return $defaults;
    }
}
