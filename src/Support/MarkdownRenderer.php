<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Support;

use ArtisanBuild\Bonfire\Models\Member;
use Illuminate\Support\HtmlString;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Converts raw Markdown into XSS-safe HTML with mention chip rendering.
 */
class MarkdownRenderer
{
    public const string MENTION_PATTERN = '/(?<![\w`])@([A-Za-z0-9][A-Za-z0-9_\-]*)/';

    public function highlightMentions(string $html): HtmlString
    {
        // Skip wrapping if the editor already embedded mentions as #mention- anchors.
        if (str_contains($html, 'href="#mention-')) {
            return new HtmlString($html);
        }

        $highlighted = preg_replace_callback(
            '/(?<=^|[>\s(\[])@([A-Za-z0-9][A-Za-z0-9_\-]*)/u',
            function (array $m): string {
                $name = htmlspecialchars($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $isBroadcast = in_array(strtolower($m[1]), ['channel', 'here', 'everyone'], true);
                $class = $isBroadcast ? 'bonfire-mention bonfire-mention-broadcast' : 'bonfire-mention';

                return '<span class="'.$class.'">@'.$name.'</span>';
            },
            $html
        ) ?? $html;

        return new HtmlString($highlighted);
    }

    public function render(string $markdown, ?int $tenantId = null): HtmlString
    {
        $chips = [];
        $withPlaceholders = $this->extractMentions($markdown, $tenantId, $chips);

        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new AutolinkExtension);

        $converter = new MarkdownConverter($environment);
        $html = (string) $converter->convert($withPlaceholders);

        foreach ($chips as $token => $chipHtml) {
            $html = str_replace($token, $chipHtml, $html);
        }

        return new HtmlString($html);
    }

    /**
     * @return list<string>
     */
    public function extractMentionNames(string $markdown): array
    {
        preg_match_all(self::MENTION_PATTERN, $markdown, $matches);

        return array_values(array_unique(array_map(trim(...), $matches[1])));
    }

    /**
     * @param-out  array<string, string>  $chips
     *
     * @param  array<string, string>  $chips
     */
    private function extractMentions(string $markdown, ?int $tenantId, array &$chips): string
    {
        $chips = [];
        $names = $this->extractMentionNames($markdown);

        if ($names === []) {
            return $markdown;
        }

        $members = Member::query()
            ->whereIn('display_name', $names)
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('display_name');

        $profileResolver = config('bonfire.user_profile_url');

        foreach ($names as $index => $name) {
            $token = '{{BONFIRE_MENTION_'.$index.'_'.bin2hex(random_bytes(4)).'}}';
            $member = $members->get($name);

            $chips[$token] = $this->buildChip($name, $member, $profileResolver);
            $markdown = preg_replace(
                '/(?<![\w`])@'.preg_quote($name, '/').'\b/',
                $token,
                $markdown,
            ) ?? $markdown;
        }

        return $markdown;
    }

    private function buildChip(string $name, ?Member $member, mixed $profileResolver): string
    {
        $escapedName = htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $label = '@'.$escapedName;

        if ($member !== null && is_callable($profileResolver)) {
            $url = $profileResolver($member);

            if (is_string($url) && $url !== '') {
                $escapedUrl = htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return '<a href="'.$escapedUrl.'" class="bonfire-mention">'.$label.'</a>';
            }
        }

        return '<span class="bonfire-mention">'.$label.'</span>';
    }
}
