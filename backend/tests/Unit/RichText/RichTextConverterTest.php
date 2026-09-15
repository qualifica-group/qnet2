<?php

use App\RichText\RichTextConverter;

it('plainTextToHtml(): null stays null, blank text becomes null (D-2)', function () {
    expect(RichTextConverter::plainTextToHtml(null, false))->toBeNull()
        ->and(RichTextConverter::plainTextToHtml("   \n\n  ", false))->toBeNull();
});

it('plainTextToHtml(): a single newline becomes <br>, a blank line becomes a new paragraph (D-10)', function () {
    expect(RichTextConverter::plainTextToHtml("Line1\nLine2", false))->toBe('<p>Line1<br>Line2</p>')
        ->and(RichTextConverter::plainTextToHtml("Line1\nLine2\n\nPara2", false))
        ->toBe('<p>Line1<br>Line2</p><p>Para2</p>');
});

it('plainTextToHtml(): escapes HTML special characters', function () {
    expect(RichTextConverter::plainTextToHtml('a < b & c', false))->toBe('<p>a &lt; b &amp; c</p>');
});

it('plainTextToHtml(): converts a mention token to a D-7 node only when asked', function () {
    expect(RichTextConverter::plainTextToHtml('Hello @[Anna](user:3) bye', true))
        ->toBe('<p>Hello <span data-type="mention" data-id="3" data-label="Anna">@Anna</span> bye</p>')
        ->and(RichTextConverter::plainTextToHtml('Hello @[Anna](user:3) bye', false))
        ->toBe('<p>Hello @[Anna](user:3) bye</p>');
});

it('htmlToPlainText(): null stays null', function () {
    expect(RichTextConverter::htmlToPlainText(null, true))->toBeNull();
});

it('round-trips a realistic multi-line, multi-paragraph, mentioning text through plainTextToHtml() and back (AC-014)', function () {
    $original = "Line1\nLine2\n\nPara2 with <tag>\n\n@[Anna](user:3) said hi";

    $html = RichTextConverter::plainTextToHtml($original, true);

    expect($html)->toBe(
        '<p>Line1<br>Line2</p>'
        .'<p>Para2 with &lt;tag&gt;</p>'
        .'<p><span data-type="mention" data-id="3" data-label="Anna">@Anna</span> said hi</p>'
    );

    expect(RichTextConverter::htmlToPlainText($html, true))->toBe($original);
});

it('htmlToPlainText(): without token restoration, a mention reads as its plain "@Label" text', function () {
    $html = RichTextConverter::plainTextToHtml('@[Anna](user:3) said hi', true);

    expect(RichTextConverter::htmlToPlainText($html, false))->toBe('@Anna said hi');
});

it('documents the lossy edge cases: 3+ consecutive newlines collapse, outer blank lines are dropped', function () {
    // Two blank lines (three \n in a row) collapse to the same single
    // paragraph break as exactly one blank line.
    expect(RichTextConverter::plainTextToHtml("A\n\n\nB", true))
        ->toBe(RichTextConverter::plainTextToHtml("A\n\nB", true));

    // A leading/trailing blank line carries no text, so it is not restored.
    $html = RichTextConverter::plainTextToHtml("\n\nA\n\n", true);
    expect(RichTextConverter::htmlToPlainText($html, true))->toBe('A');
});
