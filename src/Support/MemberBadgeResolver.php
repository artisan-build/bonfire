<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Support;

use ArtisanBuild\Bonfire\Models\Member;
use Throwable;

final class MemberBadgeResolver
{
    public function __construct(private readonly MemberBadgeRegistry $registry) {}

    /**
     * @return list<array{label: string, url: string, title: string|null}>
     */
    public function resolve(Member $member): array
    {
        $resolver = $this->registry->resolver();

        if ($resolver === null) {
            return [];
        }

        try {
            $entries = $resolver($member);

            if (! is_iterable($entries)) {
                return [];
            }

            $badges = [];

            foreach ($entries as $entry) {
                $badge = $this->normalize($entry);

                if ($badge !== null) {
                    $badges[] = $badge;
                }
            }

            return $badges;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array{label: string, url: string, title: string|null}|null
     */
    private function normalize(mixed $entry): ?array
    {
        if (! is_array($entry)
            || ! isset($entry['label'], $entry['url'])
            || ! is_string($entry['label'])
            || ! is_string($entry['url'])
            || (array_key_exists('title', $entry) && $entry['title'] !== null && ! is_string($entry['title']))) {
            return null;
        }

        return [
            'label' => $this->escape($entry['label']),
            'url' => $this->escape($entry['url']),
            'title' => isset($entry['title']) ? $this->escape($entry['title']) : null,
        ];
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
