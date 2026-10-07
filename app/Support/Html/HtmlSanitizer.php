<?php

namespace App\Support\Html;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonySanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Rich-text sanitiser (SECURITY-ARCHITECTURE.md §6, CMS-BLOCK-SCHEMA.md §8.3).
 * Applied on save, so stored HTML is always clean; output is rendered as-is only by the
 * RichText component. Scripts, styles, iframes, forms, event handlers, style attributes
 * and javascript:/data: URLs never survive.
 */
final class HtmlSanitizer
{
    private SymfonySanitizer $sanitizer;

    private SymfonySanitizer $inline;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('sub')
            ->allowElement('sup')
            ->allowElement('a', ['href', 'title', 'target', 'rel'])
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('blockquote')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('h4')
            ->allowElement('h5')
            ->allowElement('h6')
            ->allowElement('code')
            ->allowElement('pre')
            ->allowElement('hr')
            ->allowElement('table')
            ->allowElement('thead')
            ->allowElement('tbody')
            ->allowElement('tr')
            ->allowElement('th', ['colspan', 'rowspan', 'scope'])
            ->allowElement('td', ['colspan', 'rowspan'])
            ->allowElement('figure')
            ->allowElement('figcaption')
            ->allowElement('span')
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(200000);

        $this->sanitizer = new SymfonySanitizer($config);

        $this->inline = new SymfonySanitizer((new HtmlSanitizerConfig)
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('a', ['href', 'title', 'rel'])
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(20000));
    }

    public function sanitize(string $html): string
    {
        return trim($this->sanitizer->sanitize($html));
    }

    /**
     * Short formatted text such as page and news summaries: paragraphs, bold, italic and
     * links only.
     */
    public function inline(string $html): string
    {
        return trim($this->inline->sanitize($html));
    }

    /**
     * Readable plain text from HTML (search descriptions, cards, structured data).
     */
    public static function toText(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $text = html_entity_decode(strip_tags((string) preg_replace('#</p>\s*<p[^>]*>|<br\s*/?>#i', ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return $text === '' ? null : $text;
    }

    /**
     * Plain text with control characters removed (for text fields; escaped on output).
     */
    public static function plain(string $value): string
    {
        return trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value));
    }
}
