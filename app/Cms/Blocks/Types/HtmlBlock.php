<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

/**
 * HTML pasted by a trusted role (blocks.custom_html): tables, layouts and formatting that
 * the other blocks don't cover. Cleaned on save (HtmlSanitizer::html): no scripts,
 * <style>, iframes, forms or event handlers. Bootstrap classes work as on the rest of
 * the site. Others may keep or move an HTML block but not change it (see BlockTreeValidator).
 */
class HtmlBlock extends BlockType
{
    public function slug(): string
    {
        return 'html';
    }

    public function label(): string
    {
        return 'HTML';
    }

    public function description(): string
    {
        return 'Your own HTML (cleaned: no scripts, style tags or iframes).';
    }

    public function category(): string
    {
        return 'basic';
    }

    public function icon(): string
    {
        return 'bi-code-slash';
    }

    public function fields(): array
    {
        return [
            Field::html('html')->label('HTML')
                ->help('Scripts, <style>, iframes, forms and event handlers are removed when you save. Inline styles and Bootstrap classes are kept.')
                ->default('<div class="p-4 border rounded"><h2>Your heading</h2><p>Your text.</p></div>'),
        ];
    }
}
