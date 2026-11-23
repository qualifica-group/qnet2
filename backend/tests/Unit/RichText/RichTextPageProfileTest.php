<?php

use App\RichText\RichTextProfile;
use App\RichText\RichTextSanitizer;

// Pure PHP (no config/DB), like RichTextSanitizerTest.

function pageSanitize(string $html): string
{
    return (new RichTextSanitizer)->sanitize($html, false, RichTextProfile::Page);
}

it('AC-007: page profile elements survive identically', function (string $html): void {
    expect(pageSanitize($html))->toBe($html);
})->with([
    'h1' => '<h1>T</h1>',
    'collapsible heading' => '<h2 data-collapsible="true">T</h2>',
    'hr' => '<hr>',
    'task list' => '<ul data-type="taskList"><li data-type="taskItem" data-checked="true"><p>a</p></li></ul>',
    'highlight' => '<p><mark data-color="pink">a</mark></p>',
    'text color' => '<p><span data-color="orange">a</span></p>',
    'table' => '<table><thead><tr><th colspan="2">a</th></tr></thead><tbody><tr><td rowspan="2">b</td><td>c</td></tr></tbody></table>',
    'callout' => '<div data-type="callout" data-variant="warning"><p>a</p></div>',
    'collapsible' => '<div data-type="collapsible"><div data-type="collapsible-summary"><p>s</p></div><div data-type="collapsible-content"><p>b</p></div></div>',
    'date' => '<p><time data-type="date" datetime="2026-11-11">11 nov</time></p>',
    'task ref' => '<div data-type="task-ref" data-task-id="12"></div>',
    'poll' => '<div data-type="poll" data-poll-id="3"></div>',
]);

it('AC-007: unsafe markup and out-of-enum values are removed', function (string $input, string $expected): void {
    expect(pageSanitize($input))->toBe($expected);
})->with([
    'script and on*' => ['<p onclick="x()" style="a:b" class="c">a<script>x</script></p>', '<p>a</p>'],
    'javascript link' => ['<a href="javascript:alert(1)">a</a>', '<a>a</a>'],
    'callout variant' => ['<div data-type="callout" data-variant="bad"><p>a</p></div>', '<div data-type="callout"><p>a</p></div>'],
    'highlight color' => ['<mark data-color="red">a</mark>', '<mark>a</mark>'],
    'text color' => ['<span data-color="yellow">a</span>', 'a'],
    'unknown div' => ['<div data-type="other"><p>a</p></div>', '<p>a</p>'],
    'task ref id' => ['<div data-type="task-ref" data-task-id="abc"></div>', ''],
    'poll id zero' => ['<div data-type="poll" data-poll-id="0"></div>', ''],
    'checked enum' => ['<ul data-type="taskList"><li data-type="taskItem" data-checked="yes">a</li></ul>', '<ul data-type="taskList"><li data-type="taskItem">a</li></ul>'],
    'task item without type' => ['<ul><li data-checked="true">a</li></ul>', '<ul><li>a</li></ul>'],
    'ul type' => ['<ul data-type="x"><li>a</li></ul>', '<ul><li>a</li></ul>'],
    'collapsible flag' => ['<h1 data-collapsible="no">a</h1>', '<h1>a</h1>'],
    'invalid date' => ['<time data-type="date" datetime="2026-13-40">a</time>', 'a'],
    'span count' => ['<table><tbody><tr><td colspan="abc" rowspan="500">a</td></tr></tbody></table>', '<table><tbody><tr><td>a</td></tr></tbody></table>'],
    'attrs of another type' => ['<div data-type="callout" data-variant="info" data-task-id="5"></div>', '<div data-type="callout" data-variant="info"></div>'],
]);

it('AC-008: the standard profile does not accept the page elements', function (): void {
    $sanitizer = new RichTextSanitizer;

    foreach ([true, false] as $allowMentions) {
        expect($sanitizer->sanitize('<table><tbody><tr><td>a</td></tr></tbody></table>', $allowMentions))->not->toContain('<table')->not->toContain('<td')
            ->and($sanitizer->sanitize('<div data-type="callout" data-variant="info"><p>a</p></div>', $allowMentions))->not->toContain('callout')
            ->and($sanitizer->sanitize('<h1>a</h1><h4>b</h4><hr>', $allowMentions))->not->toContain('<h1')->not->toContain('<h4')->not->toContain('<hr')
            ->and($sanitizer->sanitize('<mark data-color="yellow">a</mark><time datetime="2026-01-01">a</time>', $allowMentions))->not->toContain('<mark')->not->toContain('<time')
            ->and($sanitizer->sanitize('<ul data-type="taskList"><li data-type="taskItem" data-checked="true">a</li></ul>', $allowMentions))->not->toContain('data-type')->not->toContain('data-checked');
    }
});

it('does not truncate long page content (symfony default input cap lifted)', function (): void {
    $html = str_repeat('<p>'.str_repeat('a', 200).'</p>', 500);

    expect(strlen(pageSanitize($html)))->toBe(strlen($html));
});
