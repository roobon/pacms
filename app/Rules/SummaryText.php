<?php

namespace App\Rules;

use App\Support\Html\HtmlSanitizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A page/news summary (formatted with the small editor): the visible text may be at most
 * $max characters, however much markup it carries.
 */
class SummaryText implements ValidationRule
{
    public function __construct(private readonly int $max = 1000) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(__('Enter text.'));

            return;
        }

        if (mb_strlen((string) HtmlSanitizer::toText($value)) > $this->max) {
            $fail(__('Keep the summary under :max characters.', ['max' => $this->max]));
        }
    }
}
