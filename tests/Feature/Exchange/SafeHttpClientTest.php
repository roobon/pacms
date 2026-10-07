<?php

use App\Support\Http\DownloadFailedException;
use App\Support\Http\SafeHttpClient;
use App\Support\Http\UnsafeUrlException;
use Illuminate\Support\Facades\Http;

function publicClient(array $map = []): SafeHttpClient
{
    return new SafeHttpClient(fn (string $host) => $map[$host] ?? ['93.184.216.34']);
}

it('refuses unsafe URLs before any request is made', function (string $url, array $dns = []) {
    Http::fake();

    expect(fn () => publicClient($dns)->download($url))->toThrow(UnsafeUrlException::class);
    Http::assertNothingSent();
})->with([
    'file scheme' => ['file:///etc/passwd'],
    'ftp' => ['ftp://example.org/a.jpg'],
    'credentials' => ['https://user:pass@example.org/a.jpg'],
    'odd port' => ['https://example.org:8443/a.jpg'],
    'loopback' => ['http://127.0.0.1/a.jpg'],
    'private 10/8' => ['http://10.1.2.3/a.jpg'],
    'private 192.168' => ['http://192.168.1.10/a.jpg'],
    'cloud metadata' => ['http://169.254.169.254/latest/meta-data/'],
    'cgnat' => ['http://100.64.1.1/a.jpg'],
    'ipv6 loopback' => ['http://[::1]/a.jpg'],
    'ipv4-mapped ipv6' => ['http://[::ffff:127.0.0.1]/a.jpg'],
    'unique local ipv6' => ['http://[fd00::1]/a.jpg'],
    'name resolving to loopback' => ['http://intranet.example.org/a.jpg', ['intranet.example.org' => ['127.0.0.1']]],
    'name with one private address' => ['http://mixed.example.org/a.jpg', ['mixed.example.org' => ['93.184.216.34', '10.0.0.5']]],
    'unresolvable' => ['http://nowhere.invalid/a.jpg', ['nowhere.invalid' => []]],
]);

it('downloads public files and checks every redirect', function () {
    Http::fake([
        'https://cdn.example.org/ok.jpg' => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        'https://cdn.example.org/moved.jpg' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
    ]);

    $file = publicClient()->download('https://cdn.example.org/ok.jpg');
    expect(file_get_contents($file['path']))->toBe('image-bytes')
        ->and($file['mime'])->toBe('image/jpeg')
        ->and($file['name'])->toBe('ok.jpg');
    @unlink($file['path']);

    expect(fn () => publicClient()->download('https://cdn.example.org/moved.jpg'))->toThrow(UnsafeUrlException::class);
});

it('enforces the size limit and reports HTTP errors', function () {
    Http::fake([
        'https://cdn.example.org/big.jpg' => Http::response(str_repeat('x', 2048), 200),
        'https://cdn.example.org/missing.jpg' => Http::response('', 404),
    ]);

    expect(fn () => publicClient()->download('https://cdn.example.org/big.jpg', maxBytes: 1024))->toThrow(DownloadFailedException::class)
        ->and(fn () => publicClient()->download('https://cdn.example.org/missing.jpg'))->toThrow(DownloadFailedException::class);
});
