<?php

namespace App\Services\Search;

use App\Cms\Content\ContentTypeRegistry;
use App\Models\SearchDocument;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Site search over `search_documents` (CMS-ARCHITECTURE.md §21): MySQL FULLTEXT, the query
 * always a bound parameter with the visitor's boolean operators removed (SECURITY-ARCHITECTURE.md
 * §5). Words match by their beginning; results are ranked by natural-language relevance.
 * Only published things are ever indexed.
 */
class SearchService
{
    public function __construct(private readonly ContentTypeRegistry $types) {}

    /**
     * Types a search can be limited to: key => label ("pages" => "Pages", "news" => "News"…).
     *
     * @return array<string, string>
     */
    public function types(): array
    {
        $types = ['pages' => 'Pages'];
        foreach ($this->types->all() as $type) {
            if ($type->searchable()) {
                $types[$type->key()] = $type->label();
            }
        }

        return $types;
    }

    /**
     * The query as MySQL receives it: boolean-mode operators and control characters removed.
     */
    public static function clean(string $query): string
    {
        $query = (string) preg_replace('/[+\-<>()~*"@\\\\]+/u', ' ', $query);
        $query = (string) preg_replace('/[\p{C}]+/u', ' ', $query);

        return trim((string) preg_replace('/\s+/u', ' ', $query));
    }

    /**
     * Boolean-mode query built only by us from cleaned words: each word as a prefix ("word*"),
     * any of them may match. Nothing typed by the visitor is used as an operator.
     */
    public static function prefixQuery(string $clean): string
    {
        $words = array_filter(preg_split('/\s+/u', $clean) ?: [], fn (string $word) => mb_strlen($word) >= 2);

        return implode(' ', array_map(fn (string $word) => $word.'*', array_slice($words, 0, 12)));
    }

    /**
     * @param  list<string>  $types  limit to these type keys (empty = all)
     * @return LengthAwarePaginator<int, array{type: string, type_label: string, title: string, url: string, excerpt: string, date: string|null}>
     */
    public function search(string $query, array $types = [], int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        $clean = self::clean($query);
        $prefixes = self::prefixQuery($clean);
        $labels = $this->types();
        $types = array_values(array_intersect($types, array_keys($labels)));

        // Matching uses word beginnings ("mangrove" finds "mangroves"; FULLTEXT has no stemming);
        // ranking adds natural-language relevance, which weighs rare words higher.
        $results = SearchDocument::query()
            ->select(['id', 'type', 'title', 'body', 'url', 'published_at', 'boost'])
            ->selectRaw('(MATCH(title, body) AGAINST (? IN NATURAL LANGUAGE MODE) + MATCH(title, body) AGAINST (? IN BOOLEAN MODE)) * boost AS score', [$clean, $prefixes])
            ->whereRaw('MATCH(title, body) AGAINST (? IN BOOLEAN MODE)', [$prefixes])
            ->whereIn('type', $types !== [] ? $types : array_keys($labels))
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->orderByDesc('score')
            ->orderByDesc('published_at')
            ->paginate($perPage, page: $page);

        return $results->through(fn (SearchDocument $document) => [
            'type' => $document->type,
            'type_label' => $labels[$document->type] ?? $document->type,
            'title' => $document->title,
            'url' => $document->url,
            'excerpt' => $this->excerpt((string) $document->body, $clean),
            'date' => $document->type === 'pages' ? null : $document->published_at?->toIso8601String(),
        ]);
    }

    /**
     * About 200 characters of the text around the first word of the query that occurs in it.
     */
    private function excerpt(string $body, string $query, int $length = 200): string
    {
        if ($body === '') {
            return '';
        }

        $position = null;
        foreach (preg_split('/\s+/u', $query) ?: [] as $word) {
            if (mb_strlen($word) < 2) {
                continue;
            }
            $found = mb_stripos($body, $word);
            if ($found !== false && ($position === null || $found < $position)) {
                $position = $found;
            }
        }

        $start = max(0, ($position ?? 0) - 60);
        $excerpt = mb_substr($body, $start, $length);
        // Start and end on whole words.
        if ($start > 0) {
            $excerpt = '…'.ltrim((string) preg_replace('/^\S*\s/u', '', $excerpt));
        }
        if ($start + $length < mb_strlen($body)) {
            $excerpt = rtrim((string) preg_replace('/\s\S*$/u', '', $excerpt)).'…';
        }

        return $excerpt;
    }
}
