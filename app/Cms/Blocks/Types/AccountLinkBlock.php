<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Fields\Field;

/**
 * "Sign in" for visitors, "My account" once signed in (registered users submit testimonials
 * from their account).
 */
class AccountLinkBlock extends SiteBlock
{
    public function slug(): string
    {
        return 'account-link';
    }

    public function label(): string
    {
        return 'Account link';
    }

    public function icon(): string
    {
        return 'bi-person-circle';
    }

    public function fields(): array
    {
        return [
            Field::text('sign_in_label')->label('Label for visitors')->default('Sign in')->max(60),
            Field::text('account_label')->label('Label when signed in')->default('My account')->max(60),
        ];
    }

    public function defaults(): array
    {
        return ['content' => ['sign_in_label' => 'Sign in', 'account_label' => 'My account']];
    }
}
