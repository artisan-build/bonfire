<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Support\SafeUrlGuard;

beforeEach(function (): void {
    $this->guard = new SafeUrlGuard;
});

it('rejects non-http(s) schemes', function (string $url): void {
    expect($this->guard->isSafe($url))->toBeFalse();
})->with([
    'file:///etc/passwd',
    'ftp://example.com/x',
    'gopher://example.com',
    'data:text/plain,hi',
    'javascript:alert(1)',
]);

it('rejects loopback addresses', function (string $url): void {
    expect($this->guard->isSafe($url))->toBeFalse();
})->with([
    'http://127.0.0.1/',
    'http://127.0.0.1:8080/admin',
    'http://[::1]/',
    'http://0.0.0.0/',
]);

it('rejects private network ranges', function (string $url): void {
    expect($this->guard->isSafe($url))->toBeFalse();
})->with([
    'http://10.0.0.5/',
    'http://172.16.0.1/',
    'http://192.168.1.1/',
    'http://[fd00::1]/', // RFC4193 unique local
]);

it('rejects link-local and the cloud metadata endpoint', function (string $url): void {
    expect($this->guard->isSafe($url))->toBeFalse();
})->with([
    'http://169.254.169.254/latest/meta-data/',
    'http://169.254.0.1/',
    'http://100.100.100.200/',
]);

it('rejects urls with embedded credentials', function (): void {
    expect($this->guard->isSafe('http://user:pass@93.184.216.34/'))->toBeFalse();
});

it('rejects unparseable or hostless urls', function (string $url): void {
    expect($this->guard->isSafe($url))->toBeFalse();
})->with([
    'not a url',
    'http://',
    '//example.com/x',
]);

it('allows a public literal IP', function (): void {
    // 93.184.216.34 is a documentation/public address (example.com historical).
    expect($this->guard->isSafe('http://93.184.216.34/'))->toBeTrue()
        ->and($this->guard->isSafe('https://1.1.1.1/'))->toBeTrue();
});
