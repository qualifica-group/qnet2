<?php

declare(strict_types=1);

namespace App\RichText;

use Illuminate\Support\Str;

/**
 * Visible text derived from a sanitized rich text HTML fragment (D-9): a
 * mention span renders as "@Label" (already its own text content, D-7), block
 * elements become separate lines, images contribute nothing.
 */
final class RichTextPlainText
{
    private function __construct() {}

    /**
     * @return string one line per block element, joined with "\n"
     */
    public static function toPlainText(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $blocks = RichTextDom::walkBlocks(RichTextDom::parse($html));

        return implode("\n", $blocks);
    }

    /**
     * Single-line excerpt for notifications/grid cells (D-9): blocks
     * collapse to a single space, then truncate à la `Str::limit`.
     */
    public static function excerpt(?string $html, int $limit): string
    {
        $collapsed = preg_replace('/\s+/', ' ', self::toPlainText($html)) ?? '';

        return Str::limit(trim($collapsed), $limit);
    }

    /**
     * "Empty" (D-2): no visible text AND no image — an HTML fragment made
     * only of empty tags (`<p></p>`) or whitespace text nodes is empty too.
     */
    public static function isEmpty(?string $html): bool
    {
        if ($html === null || trim($html) === '') {
            return true;
        }

        if (trim(self::toPlainText($html)) !== '') {
            return false;
        }

        $body = RichTextDom::parse($html);

        return $body->getElementsByTagName('img')->length === 0;
    }
}
