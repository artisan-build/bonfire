<?php

declare(strict_types=1);

use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Support\MarkdownRenderer;
use ArtisanBuild\Bonfire\Tests\Fixtures\TestUser;

it('renders basic markdown to HTML', function (): void {
    $html = (string) resolve(MarkdownRenderer::class)->render('Hello **world**');

    expect($html)->toContain('<strong>world</strong>');
});

it('escapes raw HTML to prevent XSS', function (): void {
    $html = (string) resolve(MarkdownRenderer::class)->render('<script>alert(1)</script>');

    expect($html)->not->toContain('<script>');
});

it('renders code blocks', function (): void {
    $markdown = "```\nSELECT 1\n```";
    $html = (string) resolve(MarkdownRenderer::class)->render($markdown);

    expect($html)->toContain('<pre>')->toContain('<code>');
});

it('renders a mention chip', function (): void {
    Bonfire::ensureMember(TestUser::query()->create(['name' => 'Grace']), 'Grace');

    $html = (string) resolve(MarkdownRenderer::class)->render('Hey @Grace please review');

    expect($html)->toContain('bonfire-mention')->toContain('@Grace');
});

it('extracts unique mention names', function (): void {
    $names = (new MarkdownRenderer)->extractMentionNames('@Ada @Grace and @Ada');

    expect($names)->toBe(['Ada', 'Grace']);
});
