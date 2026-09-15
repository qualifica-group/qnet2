<?php

namespace App\Notes\Mentions;

use App\RichText\RichText;
use App\RichText\RichTextDom;
use Illuminate\Support\Str;

/**
 * Reads the mention nodes embedded in Note::body (spec 0128, D-7): a
 * `span[data-type="mention"][data-id]` node, its own text already the
 * `@Label` shown to the reader. Off the DOM, not a regex, since D-7 replaced
 * the 0052 plain-text token `@[Name](user:12)` with an HTML node the
 * sanitizer itself validates (RichTextSanitizer::enforceMentions) — this
 * class trusts that a surviving mention span is already well-formed.
 */
final class MentionParser
{
    /**
     * User ids embedded in $html's mention nodes, in DOCUMENT order,
     * deduplicated (a user mentioned twice counts once, D-12).
     *
     * @return array<int, int>
     */
    public static function extractIds(string $html): array
    {
        $body = RichTextDom::parse($html);
        $ids = [];

        foreach (iterator_to_array($body->getElementsByTagName(RichText::MENTION_ELEMENT)) as $span) {
            if (RichTextDom::attr($span, RichText::MENTION_ATTR_TYPE) !== RichText::MENTION_DATA_TYPE) {
                continue;
            }

            $id = RichTextDom::attr($span, RichText::MENTION_ATTR_ID);

            if (RichText::isPositiveIntString($id)) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Single-line excerpt of $html for the mention notification (D-9): a
     * mention node renders as "@{current name}" — $namesById, falling back to
     * the label the node itself carries when the id no longer resolves to a
     * user (D-11). The one place mention id extraction (extractIds) and
     * mention TEXT resolution both live, so neither can drift from the
     * other's notion of what a valid mention node looks like.
     *
     * @param  array<int, string>  $namesById
     */
    public static function excerptWithNames(string $html, array $namesById, int $limit): string
    {
        $blocks = RichTextDom::walkBlocks(
            RichTextDom::parse($html),
            static fn (string $id, string $label): string => '@'.($namesById[(int) $id] ?? $label),
        );

        $collapsed = preg_replace('/\s+/', ' ', implode(' ', $blocks)) ?? '';

        return Str::limit(trim($collapsed), $limit);
    }
}
