<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

/**
 * A contact form (Phase 11A): name, e-mail, optional subject, message and consent. Messages
 * are e-mailed to the chosen address (or the site's contact e-mail) and kept in the admin's
 * Messages for 90 days. Spam: a hidden trap field, a minimum fill time and rate limits; no
 * third-party captcha (P-2).
 */
class ContactFormBlock extends BlockType
{
    public const DEFAULT_CONSENT = 'I agree that my message is stored for 90 days and used only to answer me.';

    public function slug(): string
    {
        return 'contact-form';
    }

    public function label(): string
    {
        return 'Contact form';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'bi-envelope-paper';
    }

    public function fields(): array
    {
        return [
            Field::text('heading')->label('Heading (optional)')->max(120),
            Field::email('recipient')->label('Send messages to')->help('Leave empty to use the contact e-mail from Settings → General. Visitors never see this address.'),
            Field::checkbox('show_subject')->label('Ask for a subject')->default(true),
            Field::text('consent_text')->label('Consent text')->default(self::DEFAULT_CONSENT)->max(300)->help('Shown next to a tick box the visitor must tick. Leave empty for no tick box.'),
            Field::text('button_label')->label('Button text')->default('Send message')->max(60),
            Field::textarea('thank_you')->label('Thank-you message')->default('Thank you! We have received your message and will reply soon.')->max(500),
        ];
    }

    public function defaults(): array
    {
        return ['content' => [
            'heading' => 'Send us a message',
            'show_subject' => true,
            'consent_text' => self::DEFAULT_CONSENT,
            'button_label' => 'Send message',
            'thank_you' => 'Thank you! We have received your message and will reply soon.',
        ]];
    }

    /** The recipient stays on the server: the payload never contains it. */
    public function privateFields(): array
    {
        return ['recipient'];
    }

    public function data(array $content): array
    {
        return ['has_consent' => trim((string) ($content['consent_text'] ?? '')) !== ''];
    }
}
