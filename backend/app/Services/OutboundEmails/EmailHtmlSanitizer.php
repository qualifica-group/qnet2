<?php

declare(strict_types=1);

namespace App\Services\OutboundEmails;

use App\RichText\RichTextDom;
use App\RichText\RichTextSanitizer;

/**
 * HTML sanitizer for the email domain (spec 0175, D-11): wraps the shared
 * RichTextSanitizer allow-list (no mentions) and additionally strips every
 * `<img>` outright. Unlike everywhere else rich text is used, an email
 * template's or an email's body never carries an inline image at all (D-11:
 * "immagini nel corpo fuori scope: <img> viene rimosso lato server") — even a
 * structurally valid `data-attachment-id` image is dropped here, not merely
 * stripped of its `src` the way RichTextSanitizer treats it elsewhere.
 *
 * Shared by EmailTemplateService (`email_templates.body`, this module) and
 * BE-05's outbound email write path (`outbound_emails.body`) — one
 * sanitizer, one place the D-11 rule lives.
 */
final class EmailHtmlSanitizer
{
    public function __construct(private readonly RichTextSanitizer $sanitizer) {}

    public function sanitize(string $html): string
    {
        $sanitized = $this->sanitizer->sanitize($html, allowMentions: false);
        $body = RichTextDom::parse($sanitized);

        foreach (iterator_to_array($body->getElementsByTagName('img')) as $image) {
            $image->remove();
        }

        return RichTextDom::serializeInner($body);
    }
}
