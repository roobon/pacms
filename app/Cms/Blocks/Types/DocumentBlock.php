<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

/**
 * A document from the Media Library: a PDF shown in an embedded viewer, or a download
 * card (any document). A download link is always offered, because phones and some
 * browsers cannot show embedded PDFs. Only public files can be used.
 */
class DocumentBlock extends BlockType
{
    public const HEIGHTS = ['sm' => 'Small (480 px)', 'md' => 'Medium (720 px)', 'lg' => 'Large (960 px)'];

    public function slug(): string
    {
        return 'document';
    }

    public function label(): string
    {
        return 'Document';
    }

    public function description(): string
    {
        return 'A PDF shown in the page, or a download card for any document.';
    }

    public function category(): string
    {
        return 'basic';
    }

    public function icon(): string
    {
        return 'bi-file-earmark-pdf';
    }

    public function fields(): array
    {
        return [
            Field::media('file')->accept('document')->label('Document')->required(),
            Field::text('title')->label('Title (optional)')->help('Leave empty to use the file name.')->max(191),
            Field::textarea('description')->label('Description (optional)')->max(500),
            Field::select('display', ['viewer' => 'Show the PDF in the page', 'card' => 'Download card'])->label('Show as')->default('viewer')
                ->help('Only PDFs can be shown in the page; other documents always appear as a download card.'),
            Field::select('height', self::HEIGHTS)->label('Viewer height')->default('md'),
        ];
    }
}
