<?php

use App\RichText\RichTextPlainText;

it('extracts visible text, flattening inline formatting', function () {
    expect(RichTextPlainText::toPlainText('<p>Hello <strong>World</strong></p>'))->toBe('Hello World');
});

it('separates block elements with a newline', function () {
    expect(RichTextPlainText::toPlainText('<p>A</p><p>B</p>'))->toBe("A\nB");
});

it('flattens list items to their own lines', function () {
    expect(RichTextPlainText::toPlainText('<ul><li>One</li><li>Two</li></ul>'))->toBe("One\nTwo");
});

it('renders a mention node as its own "@Label" text (D-7/D-9)', function () {
    $html = '<p><span data-type="mention" data-id="3" data-label="Anna">@Anna</span> ciao</p>';

    expect(RichTextPlainText::toPlainText($html))->toBe('@Anna ciao');
});

it('omits images from the visible text without breaking block separation', function () {
    $html = '<p>Before</p><img data-attachment-id="1" alt=""><p>After</p>';

    expect(RichTextPlainText::toPlainText($html))->toBe("Before\nAfter");
});

it('does not double the block separator for a block nested in another block (blockquote > p)', function () {
    expect(RichTextPlainText::toPlainText('<blockquote><p>Quoted</p></blockquote>'))->toBe('Quoted');
});

it('returns an empty string for null/empty-tag html', function () {
    expect(RichTextPlainText::toPlainText(null))->toBe('')
        ->and(RichTextPlainText::toPlainText('<p></p>'))->toBe('');
});

it('excerpt(): collapses blocks to a single space and truncates like Str::limit (D-9)', function () {
    expect(RichTextPlainText::excerpt('<p>A</p><p>B</p>', 10))->toBe('A B')
        ->and(RichTextPlainText::excerpt(str_repeat('<p>word</p>', 50), 20))->toBe('word word word word...');
});

it('isEmpty(): true for empty tags and null, false with visible text or an image (D-2)', function () {
    expect(RichTextPlainText::isEmpty('<p></p>'))->toBeTrue()
        ->and(RichTextPlainText::isEmpty(null))->toBeTrue()
        ->and(RichTextPlainText::isEmpty('<p>hi</p>'))->toBeFalse()
        ->and(RichTextPlainText::isEmpty('<img data-attachment-id="1" alt="">'))->toBeFalse();
});
