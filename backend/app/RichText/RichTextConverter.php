<?php

declare(strict_types=1);

namespace App\RichText;

/**
 * Plain text <-> rich text HTML, both directions of the D-10 migration (and
 * its reversible `down()`): a blank line becomes a paragraph break, a single
 * newline becomes `<br>`, and — only where asked — a `@[Name](user:12)`
 * mention token becomes a D-7 mention node and back.
 *
 * `htmlToPlainText(plainTextToHtml($text, true), true) === $text` holds for
 * ordinary multi-line text; documented lossy edges: 3+ consecutive newlines
 * collapse to one paragraph break (indistinguishable from exactly 2), and
 * leading/trailing blank lines are dropped (empty paragraphs carry no text).
 */
final class RichTextConverter
{
    private const string MENTION_TOKEN_PATTERN = '/@\[([^\]]*)\]\(user:(\d+)\)/';

    private function __construct() {}

    public static function plainTextToHtml(?string $text, bool $convertMentionTokens): ?string
    {
        if ($text === null) {
            return null;
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $paragraphs = preg_split('/\n{2,}/', $normalized) ?: [];

        $html = '';

        foreach ($paragraphs as $paragraph) {
            if (trim($paragraph) === '') {
                continue;
            }

            $lines = array_map(
                fn (string $line): string => self::renderLine($line, $convertMentionTokens),
                explode("\n", $paragraph),
            );

            $html .= '<p>'.implode('<br>', $lines).'</p>';
        }

        return $html === '' ? null : $html;
    }

    public static function htmlToPlainText(?string $html, bool $restoreMentionTokens): ?string
    {
        if ($html === null) {
            return null;
        }

        $mentionRenderer = $restoreMentionTokens
            ? static fn (string $id, string $label): string => '@['.$label.'](user:'.$id.')'
            : null;

        $blocks = RichTextDom::walkBlocks(RichTextDom::parse($html), $mentionRenderer);

        return implode("\n\n", $blocks);
    }

    private static function renderLine(string $line, bool $convertMentionTokens): string
    {
        if (! $convertMentionTokens) {
            return e($line);
        }

        $parts = preg_split(self::MENTION_TOKEN_PATTERN, $line, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return e($line);
        }

        $rendered = '';

        for ($i = 0, $count = count($parts); $i < $count; $i += 3) {
            $rendered .= e($parts[$i]);

            if ($i + 2 < $count) {
                $rendered .= self::mentionSpan($parts[$i + 2], $parts[$i + 1]);
            }
        }

        return $rendered;
    }

    private static function mentionSpan(string $id, string $label): string
    {
        $safeLabel = e($label);

        return sprintf(
            '<%1$s %2$s="%3$s" %4$s="%5$s" %6$s="%7$s">@%7$s</%1$s>',
            RichText::MENTION_ELEMENT,
            RichText::MENTION_ATTR_TYPE, RichText::MENTION_DATA_TYPE,
            RichText::MENTION_ATTR_ID, $id,
            RichText::MENTION_ATTR_LABEL, $safeLabel,
        );
    }
}
