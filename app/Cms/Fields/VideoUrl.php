<?php

namespace App\Cms\Fields;

/**
 * Parses YouTube and Vimeo links into a provider + id. Only these providers can be
 * embedded (CSP frame-src), and only through a click-to-load facade.
 */
final class VideoUrl
{
    /**
     * @return array{provider: 'youtube'|'vimeo', id: string}|null
     */
    public static function parse(string $url): ?array
    {
        $patterns = [
            'youtube' => '#^https://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})#',
            'vimeo' => '#^https://(?:www\.)?vimeo\.com/(?:video/)?(\d{6,12})#',
        ];

        foreach ($patterns as $provider => $pattern) {
            if (preg_match($pattern, trim($url), $match)) {
                return ['provider' => $provider, 'id' => $match[1]];
            }
        }

        return null;
    }
}
