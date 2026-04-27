<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Jobs;

use ArtisanBuild\Bonfire\Models\LinkPreview;
use ArtisanBuild\Bonfire\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
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

    public function handle(): void
    {
        $message = Message::query()->find($this->messageId);

        if ($message === null || $message->trashed()) {
            return;
        }

        $timeout = (int) config('bonfire.link_preview_timeout_seconds', 5);

        $response = Http::timeout($timeout)
            ->withHeaders(['User-Agent' => 'BonfireLinkPreview/1.0'])
            ->get($this->url);

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

    public function failed(Throwable $exception): void
    {
        LinkPreview::query()->updateOrCreate(
            ['message_id' => $this->messageId],
            [
                'url' => $this->url,
                'fetched_at' => now(),
                'failed' => true,
            ],
        );
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
