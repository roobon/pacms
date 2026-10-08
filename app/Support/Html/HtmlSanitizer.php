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

    private ?SymfonySanitizer $htmlBlock = null;

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
     * HTML block (trusted roles only): layout and formatting markup with classes, ids,
     * ARIA attributes and inline styles. Scripts, <style>, iframes, forms, event handlers and
     * javascript:/data: URLs are removed, and so are inline styles that load anything
     * (url(), @import, expression()).
     */
    public function html(string $html): string
    {
        $this->htmlBlock ??= new SymfonySanitizer($this->htmlBlockConfig());
        $clean = $this->htmlBlock->sanitize($html);

        $clean = (string) preg_replace_callback('/\sstyle="([^"]*)"/i', function (array $match) {
            $css = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return preg_match('/url\s*\(|@import|expression\s*\(|javascript:|behavior\s*:|-moz-binding/i', $css) ? '' : $match[0];
        }, $clean);

        return trim($clean);
    }

    private function htmlBlockConfig(): HtmlSanitizerConfig
    {
        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeMedias()
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(100000);

        $common = ['class', 'id', 'title', 'style', 'role', 'aria-label', 'aria-hidden', 'aria-describedby', 'lang', 'dir'];
        $elements = [
            'div', 'section', 'article', 'aside', 'header', 'footer', 'main', 'nav', 'span', 'p', 'br', 'hr',
            'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'b', 'em', 'i', 'u', 's', 'small', 'mark', 'sub', 'sup',
            'abbr', 'cite', 'q', 'blockquote', 'code', 'pre', 'kbd', 'time', 'address',
            'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'figure', 'figcaption', 'details', 'summary',
            'table', 'caption', 'colgroup', 'col', 'thead', 'tbody', 'tfoot', 'tr',
        ];
        foreach ($elements as $element) {
            $config = $config->allowElement($element, $common);
        }

        return $config
            ->allowElement('a', [...$common, 'href', 'target', 'rel'])
            ->allowElement('img', [...$common, 'src', 'alt', 'width', 'height', 'loading'])
            ->allowElement('th', [...$common, 'colspan', 'rowspan', 'scope'])
            ->allowElement('td', [...$common, 'colspan', 'rowspan']);
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
