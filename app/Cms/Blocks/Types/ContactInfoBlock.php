<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Fields\Field;
use App\Services\Settings\SettingsService;

/**
 * The organisation's e-mail, phone and address from Settings → General.
 */
class ContactInfoBlock extends SiteBlock
{
    public function slug(): string
    {
        return 'contact-info';
    }

    public function label(): string
    {
        return 'Contact info';
    }

    public function icon(): string
    {
        return 'bi-person-lines-fill';
    }

    public function fields(): array
    {
        return [
            Field::text('heading')->label('Heading (optional)')->max(120),
            Field::checkbox('show_email')->label('E-mail')->default(true),
            Field::checkbox('show_phone')->label('Phone')->default(true),
            Field::checkbox('show_address')->label('Address')->default(true),
            Field::select('layout', ['stacked' => 'One per line', 'inline' => 'On one line'])->label('Layout')->default('stacked'),
        ];
    }

    public function defaults(): array
    {
        return ['content' => ['show_email' => true, 'show_phone' => true, 'show_address' => true, 'layout' => 'stacked']];
    }

    public function data(array $content): array
    {
        $site = app(SettingsService::class)->group('site');

        return array_filter([
            'email' => ($content['show_email'] ?? true) ? $site['contact_email'] : null,
            'phone' => ($content['show_phone'] ?? true) ? $site['contact_phone'] : null,
            'address' => ($content['show_address'] ?? true) ? $site['address'] : null,
        ], fn ($value) => is_string($value) && $value !== '');
    }
}
