<?php

namespace App\Cms\External;

use App\Support\Html\HtmlSanitizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use SimplePie\SimplePie;
use Throwable;

/**
 * Turns a downloaded feed into normalised items (CMS-ARCHITECTURE.md §16.2, D-15):
 * JSON Feed 1.x first, RSS 2.0 / Atom (SimplePie) as the fallback.
 *
 * - XML is refused when it declares a DOCTYPE or entities (entity expansion and external
 *   entity attacks), before any parser sees it; SimplePie only ever gets this checked text
 *   and never fetches anything itself.
 * - Text is cleaned: titles and summaries become plain text, content keeps only the rich
 *   text allowlist, links must be http(s) (relative ones are made absolute).
 */
class FeedParser
{
    public const MAX_ITEMS = 200;

    private const MEDIA_RSS = 'http://search.yahoo.com/mrss/';

    public function __construct(private readonly HtmlSanitizer $html) {}

    /**
     * @return array{format: string, title: string|null, website: string|null, items: list<array<string, mixed>>}
     *
     * @throws FeedException when the document is not a feed or is not safe to read
     */
    public function parse(string $body, string $feedUrl, ?string $mime = null): array
    {
        $body = (string) preg_replace('/^\xEF\xBB\xBF/', '', $body);
        $trimmed = ltrim($body);

        if (str_starts_with($trimmed, '{') || str_contains((string) $mime, 'json')) {
            return $this->json($trimmed, $feedUrl);
        }
        if (str_starts_with($trimmed, '<')) {
            return $this->xml($trimmed, $feedUrl);
        }

        throw new FeedException(__('This address does not return a feed (JSON Feed, RSS or Atom).'));
    }

    /**
     * @return array{format: string, title: string|null, website: string|null, items: list<array<string, mixed>>}
     */
    private function json(string $body, string $feedUrl): array
    {
        try {
            $feed = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new FeedException(__('The feed is not valid JSON.'));
        }
        if (! is_array($feed) || ! str_starts_with((string) ($feed['version'] ?? ''), 'https://jsonfeed.org/version/1') || ! is_array($feed['items'] ?? null)) {
            throw new FeedException(__('This JSON is not a JSON Feed (https://jsonfeed.org).'));
        }

        $items = [];
        foreach (array_slice($feed['items'], 0, self::MAX_ITEMS) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $link = $this->url($item['url'] ?? $item['external_url'] ?? null, $feedUrl);
            $id = $this->string($item['id'] ?? null) ?? $link;
            if ($id === null) {
                continue;
            }
            $content = $this->string($item['content_html'] ?? null) ?? (isset($item['content_text']) ? '<p>'.e((string) $item['content_text']).'</p>' : null);
            $authors = is_array($item['authors'] ?? null) ? $item['authors'] : (is_array($item['author'] ?? null) ? [$item['author']] : []);
            $tags = is_array($item['tags'] ?? null) ? $item['tags'] : [];

            $items[] = $this->item(
                id: $id,
                title: $this->string($item['title'] ?? null),
                link: $link,
                summary: $this->string($item['summary'] ?? null),
                content: $content,
                author: $this->string($authors[0]['name'] ?? null),
                category: $this->string($tags[0] ?? null),
                image: $this->url($item['image'] ?? $item['banner_image'] ?? null, $feedUrl),
                published: $this->string($item['date_published'] ?? null),
                updated: $this->string($item['date_modified'] ?? null),
            );
        }

        return [
            'format' => 'json',
            'title' => $this->plain($this->string($feed['title'] ?? null)),
            'website' => $this->url($feed['home_page_url'] ?? null, $feedUrl),
            'items' => $items,
        ];
    }

    /**
     * @return array{format: string, title: string|null, website: string|null, items: list<array<string, mixed>>}
     */
    private function xml(string $body, string $feedUrl): array
    {
        if (preg_match('/<!(DOCTYPE|ENTITY)/i', $body)) {
            throw new FeedException(__('The feed declares a document type or entities, which PACMS does not accept for safety.'));
        }

        // SimplePie 1.9 triggers PHP 8.5 deprecation notices inside its own code; they are
        // harmless and would flood the log on every sync.
        set_error_handler(fn () => true, E_DEPRECATED | E_USER_DEPRECATED);
        try {
            return $this->readXml($body, $feedUrl);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @return array{format: string, title: string|null, website: string|null, items: list<array<string, mixed>>}
     */
    private function readXml(string $body, string $feedUrl): array
    {
        $pie = new SimplePie;
        $pie->set_raw_data($body);
        $pie->enable_cache(false);
        $pie->enable_order_by_date(false);
        $pie->set_item_limit(self::MAX_ITEMS);
        if (! $pie->init() || $pie->error()) {
            throw new FeedException(__('The feed could not be read: it is not valid RSS or Atom.'));
        }

        $items = [];
        foreach ($pie->get_items(0, self::MAX_ITEMS) as $item) {
            $link = $this->url($item->get_permalink(), $feedUrl);
            $id = $this->string($item->get_id(false)) ?? $link;
            if ($id === null) {
                continue;
            }
            $enclosure = $item->get_enclosure();
            $image = null;
            if ($enclosure !== null) {
                $image = $enclosure->get_thumbnail() ?: (str_starts_with((string) $enclosure->get_type(), 'image/') || preg_match('/\.(jpe?g|png|webp|gif)(\?|$)/i', (string) $enclosure->get_link()) ? $enclosure->get_link() : null);
            }
            $category = $item->get_category();
            // Media RSS groups (YouTube channel and playlist feeds): thumbnail and description.
            $group = $item->get_item_tags(self::MEDIA_RSS, 'group')[0]['child'][self::MEDIA_RSS] ?? [];
            $image ??= $group['thumbnail'][0]['attribs']['']['url'] ?? ($item->get_item_tags(self::MEDIA_RSS, 'thumbnail')[0]['attribs']['']['url'] ?? null);
            $mediaDescription = $group['description'][0]['data'] ?? null;

            $items[] = $this->item(
                id: $id,
                title: $this->string($item->get_title()),
                link: $link,
                summary: $this->string($item->get_description(true)) ?? $this->string($mediaDescription),
                content: $this->string($item->get_content(false)),
                author: $this->string($item->get_author()?->get_name()),
                category: $this->string($category?->get_label()),
                image: $this->url($image, $feedUrl),
                published: $this->string($item->get_date('c') ?: null),
                updated: $this->string($item->get_updated_date('c') ?: null),
            );
        }

        $type = $pie->get_type();

        return [
            'format' => ($type & SimplePie::TYPE_ATOM_ALL) ? 'atom' : 'rss',
            'title' => $this->plain($this->string($pie->get_title())),
            'website' => $this->url($pie->get_link(), $feedUrl),
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(string $id, ?string $title, ?string $link, ?string $summary, ?string $content, ?string $author, ?string $category, ?string $image, ?string $published, ?string $updated): array
    {
        $description = $content !== null ? ($this->html->sanitize($content) ?: null) : null;
        $excerpt = $this->plain($summary) ?? $this->plain($content);

        return [
            'external_id' => mb_substr($id, 0, 255),
            'title' => $this->limit($this->plain($title), 512),
            'link' => $link,
            'excerpt' => $excerpt === null ? null : Str::limit($excerpt, 400),
            'description' => $description,
            'author' => $this->limit($this->plain($author), 255),
            'category' => $this->limit($this->plain($category), 255),
            'image_url' => $image,
            'published_at' => $this->date($published),
            'source_updated_at' => $this->date($updated),
        ];
    }

    private function string(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function plain(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return $text === '' ? null : $text;
    }

    private function limit(?string $value, int $max): ?string
    {
        return $value === null ? null : mb_substr($value, 0, $max);
    }

    /**
     * An http(s) address (relative ones resolved against the feed), or null.
     */
    private function url(mixed $value, string $base): ?string
    {
        $url = $this->string($value);
        if ($url === null) {
            return null;
        }
        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        } elseif (! preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
            $parts = parse_url($base);
            if (! isset($parts['scheme'], $parts['host'])) {
                return null;
            }
            $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
            $url = str_starts_with($url, '/') ? $origin.$url : $origin.rtrim(dirname($parts['path'] ?? '/'), '/').'/'.$url;
        }

        return preg_match('#^https?://[^\s<>"]+$#i', $url) && strlen($url) <= 2048 ? $url : null;
    }

    private function date(?string $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }
        try {
            $date = Carbon::parse($value)->utc();

            // Dates far in the future are feed errors; they would stay on top for ever.
            return $date->isAfter(now()->addDay()) ? now()->utc() : $date;
        } catch (Throwable) {
            return null;
        }
    }
}
