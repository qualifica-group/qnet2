<?php

declare(strict_types=1);

namespace App\RichText;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;

/**
 * Low-level fragment parsing/serialization/walking shared by the sanitizer's
 * post-processing pass, the plain-text extractor and the converter — the one
 * place that touches PHP 8.4's `Dom\HTMLDocument` directly so every other
 * class in this namespace works against a small, typed surface instead.
 */
final class RichTextDom
{
    /**
     * Tags whose subtree is rendered as ONE line of plain text, separated
     * from the next block by a newline (RichTextPlainText, RichTextConverter).
     */
    private const array BLOCK_TAGS = ['p', 'h2', 'h3', 'li', 'blockquote', 'pre'];

    private function __construct() {}

    /**
     * Parse an HTML fragment (no doctype/html/body required — the parser
     * supplies them) and return its `<body>`, the fragment's real root.
     */
    public static function parse(string $html): Element
    {
        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);

        /** @var Element $body */
        $body = $document->getElementsByTagName('body')->item(0);

        return $body;
    }

    /**
     * `Dom\Element::getAttribute()` returns null (not '') for a missing
     * attribute — every caller in this namespace wants the empty-string
     * shape instead, so it is normalised in this one place.
     */
    public static function attr(Element $element, string $name): string
    {
        return $element->getAttribute($name) ?? '';
    }

    /**
     * Serialize $container's children back to an HTML fragment string (the
     * container itself, typically `<body>`, is never part of the output).
     */
    public static function serializeInner(Element $container): string
    {
        $document = $container->ownerDocument;
        $html = '';

        foreach ($container->childNodes as $child) {
            $html .= $document->saveHtml($child);
        }

        return $html;
    }

    /**
     * Replace $element with its own children, dropping only the tag —
     * used to reduce an invalid mention `span` (or, with mentions
     * disallowed, every mention span) to its plain text (D-1/D-7).
     */
    public static function unwrap(Element $element): void
    {
        $children = iterator_to_array($element->childNodes);

        if ($children === []) {
            $element->remove();

            return;
        }

        $element->replaceWith(...$children);
    }

    /**
     * Walk $container's subtree and split it into block-level lines of
     * plain text (RichTextPlainText::toPlainText, RichTextConverter::
     * htmlToPlainText): `img` is omitted, `br` becomes a newline WITHIN a
     * block, and a mention span (D-7) is rendered by $mentionRenderer when
     * given, or otherwise left to its own text content ("@Label").
     *
     * @param  (callable(string $id, string $label): string)|null  $mentionRenderer
     * @return list<string>
     */
    public static function walkBlocks(Element $container, ?callable $mentionRenderer = null): array
    {
        $blocks = [];
        $buffer = '';

        self::walk($container, $blocks, $buffer, $mentionRenderer);
        self::flush($blocks, $buffer);

        return $blocks;
    }

    /**
     * @param  list<string>  $blocks
     * @param  (callable(string $id, string $label): string)|null  $mentionRenderer
     */
    private static function walk(Node $node, array &$blocks, string &$buffer, ?callable $mentionRenderer): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof Text) {
                $buffer .= $child->data;

                continue;
            }

            if (! $child instanceof Element) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if ($tag === 'img') {
                continue;
            }

            if ($tag === 'br') {
                $buffer .= "\n";

                continue;
            }

            if ($mentionRenderer !== null
                && $tag === RichText::MENTION_ELEMENT
                && self::attr($child, RichText::MENTION_ATTR_TYPE) === RichText::MENTION_DATA_TYPE) {
                $buffer .= $mentionRenderer(
                    self::attr($child, RichText::MENTION_ATTR_ID),
                    self::attr($child, RichText::MENTION_ATTR_LABEL),
                );

                continue;
            }

            if (in_array($tag, self::BLOCK_TAGS, true)) {
                self::flush($blocks, $buffer);
                self::walk($child, $blocks, $buffer, $mentionRenderer);
                self::flush($blocks, $buffer);

                continue;
            }

            // Inline/container passthrough (strong, em, a, ul, ol, span, ...).
            self::walk($child, $blocks, $buffer, $mentionRenderer);
        }
    }

    /**
     * @param  list<string>  $blocks
     */
    private static function flush(array &$blocks, string &$buffer): void
    {
        $trimmed = trim($buffer);

        if ($trimmed !== '') {
            $blocks[] = $trimmed;
        }

        $buffer = '';
    }
}
