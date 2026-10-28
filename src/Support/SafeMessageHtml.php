<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Support;

/**
 * Helpers for assembling stored message-body HTML from user-controlled input.
 *
 * Message bodies are rendered UNESCAPED in the UI — the renderer passes embedded
 * HTML through by design so join buttons, forward blockquotes and mention
 * anchors work (see {@see MarkdownRenderer} and message-list.blade.php). That
 * makes any user-controlled value interpolated into a body without escaping a
 * stored-XSS vector. Centralising the escaping here keeps it consistent instead
 * of being re-derived ad-hoc at each call site.
 */
final class SafeMessageHtml
{
    /**
     * Escape a user-controlled value for safe interpolation into a message body
     * that will later be rendered unescaped.
     */
    public static function escape(?string $value): string
    {
        return e((string) $value);
    }

    /**
     * Build a mention anchor that is safe to embed in a stored message body.
     *
     * The logical slug (spaces → dashes) mirrors the composer's client-side
     * convention (message-composer.blade.php: `name.replace(/\s+/g, '-')`).
     * There is no `id="mention-…"` jump target anywhere — the only hash handler
     * matches `#m-<id>` — so the anchor is an inert in-page link; escaping the
     * slug for the attribute therefore cannot desync it from any target. Both
     * the href slug and the visible label are escaped so a display name
     * containing quotes or angle brackets cannot break out of the attribute or
     * inject markup. For names without HTML metacharacters the output is
     * byte-identical to the previous (unescaped) form.
     */
    public static function mention(string $displayName): string
    {
        $slug = str_replace(' ', '-', $displayName);

        return sprintf(
            '<a href="#mention-%s">@%s</a>',
            e($slug),
            e($displayName),
        );
    }
}
