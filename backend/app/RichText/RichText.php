<?php

declare(strict_types=1);

namespace App\RichText;

/**
 * Shared identifiers for the rich text domain (spec 0128): the reserved
 * attachment collection for embedded images (D-3) and the DOM vocabulary
 * (element/attribute names) the sanitizer, the image processor, the copier
 * and the plain-text/converter helpers all agree on — kept here once so
 * none of them can drift from another.
 */
final class RichText
{
    /**
     * Attachment collection reserved for images embedded in rich text
     * fields. Never exposed through the generic attachments endpoints
     * (D-6) — only the owning record's own content references it.
     */
    public const string ATTACHMENT_COLLECTION = 'rich_text';

    /** `img` attribute carrying the owning attachment's id (D-1/D-3/D-4). */
    public const string IMAGE_ATTR_ID = 'data-attachment-id';

    /** Element used for a mention node (D-7), notes only. */
    public const string MENTION_ELEMENT = 'span';

    public const string MENTION_ATTR_TYPE = 'data-type';

    public const string MENTION_ATTR_ID = 'data-id';

    public const string MENTION_ATTR_LABEL = 'data-label';

    /** Value `data-type` must have for a `span` to be a valid mention node. */
    public const string MENTION_DATA_TYPE = 'mention';

    private function __construct() {}

    /**
     * Whether $value is a positive integer written in plain decimal, no
     * leading zero — the only shape a `data-attachment-id`/`data-id` is
     * trusted in; anything else is treated as absent.
     */
    public static function isPositiveIntString(string $value): bool
    {
        return (bool) preg_match('/^[1-9][0-9]*$/', $value);
    }
}
