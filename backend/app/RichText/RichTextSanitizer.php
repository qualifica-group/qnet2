<?php

declare(strict_types=1);

namespace App\RichText;

use Dom\Element;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * The single source of truth for the rich text allow-list (D-1): only these
 * elements/attributes survive, links are restricted to http/https/mailto and
 * forced to `rel="noopener noreferrer nofollow" target="_blank"`, images only
 * ever carry `data-attachment-id`/`alt` (no `src` is ever persisted), and a
 * mention node (D-7) survives ONLY when notes explicitly allow it — reduced
 * to its plain "@Name" text everywhere else.
 *
 * Two passes: symfony/html-sanitizer strips everything not explicitly
 * allowed (elements, attributes, link/media schemes); a second DOM pass
 * (RichTextDom) validates the semantics the sanitizer's attribute allow-list
 * cannot express — a `data-attachment-id`/`data-id` must be a positive
 * integer, a mention `span` must actually carry `data-type="mention"`.
 */
final class RichTextSanitizer
{
    /**
     * @var array<string, list<string>>
     */
    private const array ALLOWED_ELEMENTS = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'em' => [],
        'u' => [],
        's' => [],
        'h2' => [],
        'h3' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'blockquote' => [],
        'pre' => [],
        'code' => [],
        'a' => ['href'],
    ];

    private const array LINK_SCHEMES = ['http', 'https', 'mailto'];

    private const string LINK_REL = 'noopener noreferrer nofollow';

    public function sanitize(string $html, bool $allowMentions): string
    {
        $sanitizer = new HtmlSanitizer($this->buildConfig($allowMentions));
        $firstPass = $sanitizer->sanitize($html);

        $body = RichTextDom::parse($firstPass);

        $this->enforceLinks($body);
        $this->enforceImages($body);

        if ($allowMentions) {
            $this->enforceMentions($body);
        }

        return RichTextDom::serializeInner($body);
    }

    private function buildConfig(bool $allowMentions): HtmlSanitizerConfig
    {
        $config = new HtmlSanitizerConfig;

        foreach (self::ALLOWED_ELEMENTS as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }

        $config = $config
            ->allowElement('img', [RichText::IMAGE_ATTR_ID, 'alt'])
            ->allowLinkSchemes(self::LINK_SCHEMES);

        if ($allowMentions) {
            return $config->allowElement(RichText::MENTION_ELEMENT, [
                RichText::MENTION_ATTR_TYPE,
                RichText::MENTION_ATTR_ID,
                RichText::MENTION_ATTR_LABEL,
            ]);
        }

        // Mentions are notes-only (D-7): elsewhere the tag itself is
        // dropped but its text ("@Name") is retained (D-1/D-7), same as
        // an unrecognised span the editor never produced.
        return $config->blockElement(RichText::MENTION_ELEMENT);
    }

    private function enforceLinks(Element $body): void
    {
        foreach (iterator_to_array($body->getElementsByTagName('a')) as $anchor) {
            if ($anchor->hasAttribute('href')) {
                $anchor->setAttribute('rel', self::LINK_REL);
                $anchor->setAttribute('target', '_blank');
            }
        }
    }

    /**
     * `data-attachment-id` must be a positive integer or the image carries
     * no verifiable owner reference and is removed outright (D-1/D-4); `alt`
     * is always present on the surviving output (data_contract shape).
     */
    private function enforceImages(Element $body): void
    {
        foreach (iterator_to_array($body->getElementsByTagName('img')) as $img) {
            if (! RichText::isPositiveIntString(RichTextDom::attr($img, RichText::IMAGE_ATTR_ID))) {
                $img->remove();

                continue;
            }

            if (! $img->hasAttribute('alt')) {
                $img->setAttribute('alt', '');
            }
        }
    }

    private function enforceMentions(Element $body): void
    {
        foreach (iterator_to_array($body->getElementsByTagName(RichText::MENTION_ELEMENT)) as $span) {
            $isValidMention = RichTextDom::attr($span, RichText::MENTION_ATTR_TYPE) === RichText::MENTION_DATA_TYPE
                && RichText::isPositiveIntString(RichTextDom::attr($span, RichText::MENTION_ATTR_ID))
                && trim(RichTextDom::attr($span, RichText::MENTION_ATTR_LABEL)) !== '';

            if (! $isValidMention) {
                RichTextDom::unwrap($span);
            }
        }
    }
}
