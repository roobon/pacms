<?php

namespace App\Cms\External\Facebook;

use App\Cms\External\FeedException;
use App\Support\Http\DownloadFailedException;
use App\Support\Http\SafeHttpClient;
use App\Support\Http\UnsafeUrlException;

/**
 * Minimal Meta Graph API client (GET only), through the SSRF-guarded HTTP client. Tokens go
 * in the query string to graph.facebook.com only and never appear in error messages.
 */
class GraphClient
{
    public const HOST = 'https://graph.facebook.com';

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     *
     * @throws FeedException with a message for admins
     */
    public function get(string $path, array $query, ?string $token): array
    {
        $version = (string) config('services.meta.graph_version', 'v26.0');
        $url = self::HOST.'/'.$version.'/'.ltrim($path, '/').'?'.http_build_query($query + ($token === null ? [] : ['access_token' => $token]));

        try {
            $response = app(SafeHttpClient::class)->fetch($url, 2 * 1024 * 1024, 20);
        } catch (DownloadFailedException $e) {
            // 400/401/403: Facebook refused the token or the permission (expired, revoked…).
            if (preg_match('/HTTP (400|401|403)/', $e->getMessage())) {
                throw new FeedException(__('Facebook refused the request: the access token may have expired or lost its permissions. Reconnect the Page.'), previous: $e);
            }
            throw new FeedException(__('Facebook could not be reached: :reason', ['reason' => $e->getMessage()]), previous: $e);
        } catch (UnsafeUrlException $e) {
            throw new FeedException(__('Facebook could not be reached.'), previous: $e);
        }

        $data = json_decode($response['body'], true);
        if (! is_array($data)) {
            throw new FeedException(__('Facebook sent an answer PACMS could not read.'));
        }
        if (isset($data['error'])) {
            throw new FeedException(__('Facebook answered with an error: :message', ['message' => mb_substr((string) ($data['error']['message'] ?? 'unknown'), 0, 200)]));
        }

        return $data;
    }

    /**
     * The OAuth dialog address (Connect with Facebook).
     */
    public static function dialogUrl(string $redirect, string $state): string
    {
        $version = (string) config('services.meta.graph_version', 'v26.0');

        return 'https://www.facebook.com/'.$version.'/dialog/oauth?'.http_build_query([
            'client_id' => config('services.meta.app_id'),
            'redirect_uri' => $redirect,
            'state' => $state,
            'scope' => 'pages_show_list,pages_read_engagement,pages_read_user_content',
            'response_type' => 'code',
        ]);
    }
}
