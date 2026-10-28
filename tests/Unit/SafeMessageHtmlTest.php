<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Support\SafeMessageHtml;

it('leaves a benign display name byte-identical in a mention anchor', function (): void {
    expect(SafeMessageHtml::mention('Grace Hopper'))
        ->toBe('<a href="#mention-Grace-Hopper">@Grace Hopper</a>');
});

it('escapes a quote so it cannot break out of the href attribute', function (): void {
    $html = SafeMessageHtml::mention('a" onmouseover="alert(1)');

    expect($html)->not->toContain('a" onmouseover="alert(1)')
        ->and($html)->toContain('&quot;')
        ->and(substr_count($html, '<a href="#mention-'))->toBe(1);
});

it('escapes angle brackets so a name cannot inject markup', function (): void {
    $html = SafeMessageHtml::mention('</a><script>alert(1)</script>');

    expect($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;');
});

it('escapes arbitrary user values for body interpolation', function (): void {
    expect(SafeMessageHtml::escape('x" <b>'))->toBe('x&quot; &lt;b&gt;')
        ->and(SafeMessageHtml::escape(null))->toBe('');
});
