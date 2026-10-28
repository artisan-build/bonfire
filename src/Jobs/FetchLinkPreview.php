<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Jobs;

use ArtisanBuild\Bonfire\Models\LinkPreview;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Message;
use ArtisanBuild\Bonfire\Models\Room;
use ArtisanBuild\Bonfire\Support\SafeUrlGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Fetches Open Graph metadata for the first URL in a Bonfire message.
 */
class FetchLinkPreview implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $messageId, public string $url) {}

    public static function extractFirstUrl(string $body): ?string
    {
        if (preg_match('/https?:\/\/[^\s<>\]\)]+/i', $body, $matches) === 1) {
            return rtrim($matches[0], '.,;:!?');
        }

        return null;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(SafeUrlGuard $guard): void
    {
        $message = Message::query()->with(['member', 'room'])->find($this->messageId);

        if ($message === null || $message->trashed() || ! $this->messageIsAccessible($message)) {
            return;
        }

        $timeout = (int) config('bonfire.link_preview_timeout_seconds', 5);

        $response = $this->fetchSafely($this->url, $guard, $timeout);

        if (! $response->successful()) {
            $response->throw();
        }

        $meta = $this->parseMetadata($response->body());

        LinkPreview::query()->updateOrCreate(
            ['message_id' => $this->messageId],
            [
                'url' => $this->url,
                'title' => $meta['title'] ?? null,
                'description' => $meta['description'] ?? null,
                'image_url' => $meta['image'] ?? null,
                'site_name' => $meta['site_name'] ?? null,
                'fetched_at' => now(),
                'failed' => false,
            ],
        );
    }

    /**
     * Fetch the URL with SSRF protection: validate every URL before the request,
     * disable the HTTP client's automatic redirects, and manually follow a
     * bounded number of redirects so each hop is re-validated against the guard.
     */
    private function fetchSafely(string $url, SafeUrlGuard $guard, int $timeout): Response
    {
        $maxRedirects = 5;

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            if (! $guard->isSafe($url)) {
                throw new RuntimeException('Refusing to fetch unsafe or non-public URL for link preview.');
            }

            $response = Http::timeout($timeout)
                ->withHeaders(['User-Agent' => 'BonfireLinkPreview/1.0'])
                ->withOptions(['allow_redirects' => false])
                ->get($url);

            if (! $response->redirect()) {
                return $response;
            }

            $location = $response->header('Location');

            if ($location === '') {
                return $response;
            }

            // Resolve relative redirects against the current absolute URL.
            $url = $this->resolveRedirectTarget($url, $location);
        }

        throw new RuntimeException('Too many redirects while fetching link preview.');
    }

    private function resolveRedirectTarget(string $base, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }

        $parts = parse_url($base);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $location;
        }

        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $path = $parts['path'] ?? '/';
        $dir = substr($path, 0, (int) strrpos($path, '/') + 1);

        return $origin.$dir.$location;
    }

    public function failed(Throwable $exception): void
    {
        $message = Message::query()->with(['member', 'room'])->find($this->messageId);

        if ($message === null || ! $this->messageIsAccessible($message)) {
            return;
        }

        LinkPreview::query()->updateOrCreate(
            ['message_id' => $this->messageId],
            [
                'url' => $this->url,
                'fetched_at' => now(),
                'failed' => true,
            ],
        );
    }

    private function messageIsAccessible(Message $message): bool
    {
        $member = $message->getRelationValue('member');
        $room = $message->getRelationValue('room');

        if (! $room instanceof Room) {
            return false;
        }

        return $room->access_gate === null
            || ($member instanceof Member && $room->isAccessibleBy($member));
    }

    /**
     * @return array{title?: string|null, description?: string|null, image?: string|null, site_name?: string|null}
     */
    private function parseMetadata(string $html): array
    {
        $out = [];

        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m) === 1) {
            $out['title'] = $m[1];
        } elseif (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m) === 1) {
            $out['title'] = trim(html_entity_decode($m[1]));
        }

        if (preg_match('/<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m) === 1) {
            $out['description'] = $m[1];
        }

        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m) === 1) {
            $out['image'] = $m[1];
        }

        if (preg_match('/<meta[^>]+property=["\']og:site_name["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m) === 1) {
            $out['site_name'] = $m[1];
        }

        return $out;
    }
}
