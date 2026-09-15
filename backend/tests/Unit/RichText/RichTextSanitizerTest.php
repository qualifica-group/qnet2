<?php

use App\RichText\RichTextSanitizer;

// Pure PHP class (no config/DB): runs against the plain PHPUnit TestCase the
// Unit suite defaults to, no `uses()` override needed.

it('drops script/style tags and their content entirely, not left as text (AC-001)', function () {
    $sanitizer = new RichTextSanitizer;

    $html = '<p>Hello</p><script>alert(1)</script><style>body{color:red}</style>';

    expect($sanitizer->sanitize($html, false))->toBe('<p>Hello</p>');
});

it('drops on*/style/class attributes and the iframe element (AC-001)', function () {
    $sanitizer = new RichTextSanitizer;

    $html = '<p onclick="alert(1)" style="color:red" class="x">Hi</p><iframe src="https://evil.example"></iframe>';

    expect($sanitizer->sanitize($html, false))->toBe('<p>Hi</p>');
});

it('drops a remote img src and requires a positive-int data-attachment-id (AC-001)', function () {
    $sanitizer = new RichTextSanitizer;

    expect($sanitizer->sanitize('<img src="https://evil.example/x.png" alt="x">', false))->toBe('')
        ->and($sanitizer->sanitize('<img src="https://evil.example/x.png" data-attachment-id="7" alt="x">', false))
        ->toBe('<img data-attachment-id="7" alt="x">');
});

it('drops a javascript: link href but keeps the tag, forces rel/target on a real link (AC-001)', function () {
    $sanitizer = new RichTextSanitizer;

    expect($sanitizer->sanitize('<a href="javascript:alert(1)">bad</a>', false))->toBe('<a>bad</a>')
        ->and($sanitizer->sanitize('<a href="https://example.com">good</a>', false))
        ->toBe('<a href="https://example.com" rel="noopener noreferrer nofollow" target="_blank">good</a>');
});

it('keeps every D-1 allowed element untouched', function () {
    $sanitizer = new RichTextSanitizer;

    $html = '<p>p</p><h2>h2</h2><h3>h3</h3><ul><li>li</li></ul><ol><li>li</li></ol>'
        .'<blockquote>bq</blockquote><pre><code>code</code></pre>'
        .'<strong>s</strong><em>e</em><u>u</u><s>del</s><br>';

    expect($sanitizer->sanitize($html, false))->toBe($html);
});

it('keeps a valid mention node when mentions are allowed, stripping any extra attribute (AC-002)', function () {
    $sanitizer = new RichTextSanitizer;

    $html = '<p><span data-type="mention" data-id="12" data-label="Anna Bianchi" class="x">@Anna Bianchi</span> hi</p>';

    expect($sanitizer->sanitize($html, true))
        ->toBe('<p><span data-type="mention" data-id="12" data-label="Anna Bianchi">@Anna Bianchi</span> hi</p>');
});

it('reduces a mention node to its plain text when mentions are disallowed (AC-002)', function () {
    $sanitizer = new RichTextSanitizer;

    $html = '<p><span data-type="mention" data-id="12" data-label="Anna Bianchi">@Anna Bianchi</span> hi</p>';

    expect($sanitizer->sanitize($html, false))->toBe('<p>@Anna Bianchi hi</p>');
});

it('unwraps a mention span with an invalid data-id even when mentions are allowed', function () {
    $sanitizer = new RichTextSanitizer;

    expect($sanitizer->sanitize('<p><span data-type="mention" data-id="abc" data-label="X">@X</span></p>', true))
        ->toBe('<p>@X</p>');
});
