<?php

namespace App\Support\Html;

use Illuminate\Support\Facades\Storage;
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
            ->allowElement('p', ['class'])
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
            ->allowElement('h2', ['class'])
            ->allowElement('h3', ['class'])
            ->allowElement('h4', ['class'])
            ->allowElement('h5', ['class'])
            ->allowElement('h6', ['class'])
            ->allowElement('code')
            ->allowElement('pre')
            ->allowElement('hr')
            ->allowElement('table')
            ->allowElement('thead')
            ->allowElement('tbody')
            ->allowElement('tr')
            ->allowElement('th', ['colspan', 'rowspan', 'scope'])
            ->allowElement('td', ['colspan', 'rowspan'])
            ->allowElement('figure', ['class'])
            ->allowElement('figcaption')
            ->allowElement('img', ['src', 'alt', 'width', 'height', 'data-media'])
            ->allowElement('span', ['class'])
            ->allowElement('mark', ['class'])
            ->allowElement('div', ['class'])
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeMedias()
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

    /**
     * Class names rich text may carry (CMS-BLOCK-SCHEMA.md §8.3): alignment, theme text and
     * highlight colours, highlight boxes and image placement. Any other class is removed.
     */
    public const RICH_TEXT_CLASSES = [
        'pa-align-center', 'pa-align-end',
        'pa-text-primary', 'pa-text-secondary', 'pa-text-accent', 'pa-text-muted', 'pa-text-success', 'pa-text-warning', 'pa-text-danger',
        'pa-mark-primary', 'pa-mark-secondary', 'pa-mark-accent', 'pa-mark-muted', 'pa-mark-success', 'pa-mark-warning', 'pa-mark-danger',
        'pa-callout', 'pa-callout--info', 'pa-callout--success', 'pa-callout--warning', 'pa-callout--note',
        'pa-figure', 'pa-figure--full', 'pa-figure--wide', 'pa-figure--left', 'pa-figure--right',
        // Earlier fixed list (span classes).
        'text-primary', 'text-accent', 'lead', 'small', 'visually-hidden',
    ];

    public function sanitize(string $html): string
    {
        $clean = $this->sanitizer->sanitize($html);

        // Only listed classes survive.
        $clean = (string) preg_replace_callback('/\sclass="([^"]*)"/i', function (array $match) {
            $kept = array_values(array_intersect(preg_split('/\s+/', trim(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?: [], self::RICH_TEXT_CLASSES));

            return $kept === [] ? '' : ' class="'.e(implode(' ', array_unique($kept))).'"';
        }, $clean);

        // Images only from this site's Media Library (R-2): others are removed, with an empty
        // figure they leave behind.
        $prefix = $this->mediaPrefix();
        $clean = (string) preg_replace_callback('/<img\b[^>]*>/i', function (array $match) use ($prefix) {
            if (! preg_match('/\ssrc="([^"]*)"/i', $match[0], $src)) {
                return '';
            }
            $url = html_entity_decode($src[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return str_starts_with($url, $prefix) || str_starts_with($url, (string) parse_url($prefix, PHP_URL_PATH)) ? $match[0] : '';
        }, $clean);
        $clean = (string) preg_replace('#<figure\b[^>]*>\s*(<figcaption>.*?</figcaption>)?\s*</figure>#is', '', $clean);

        return trim($clean);
    }

    /**
     * Where public library files are served (e.g. https://site.example/storage/media/).
     */
    private function mediaPrefix(): string
    {
        $disk = (string) config('pacms.media.disk');
        $url = rtrim((string) Storage::disk($disk)->url('media'), '/').'/';

        return $url;
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
