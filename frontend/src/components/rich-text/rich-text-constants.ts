/**
 * Frontend mirror of `config/rich_text.php` (spec 0128 D-5): the client
 * enforces the same limits before sending so the user gets instant feedback,
 * but the server is the actual defense — these values must stay in sync with
 * the backend config, never diverge silently.
 */

/** Max size of a single image, in bytes (backend `image_max_kb` 5120 KB). */
export const RICH_TEXT_IMAGE_MAX_BYTES = 5 * 1024 * 1024

/** Max number of NEW (unsaved, `data:` URI) images per field, per save. */
export const RICH_TEXT_MAX_NEW_IMAGES = 10

/** Max visible text length of a rich text field (backend `note_text_max`). */
export const RICH_TEXT_NOTE_TEXT_MAX = 5000

/** MIME types accepted for a pasted/dropped/picked image (D-1, D-3). */
export const RICH_TEXT_ALLOWED_IMAGE_MIME_TYPES = [
  'image/png',
  'image/jpeg',
  'image/gif',
  'image/webp',
] as const

/** Link schemes accepted by the toolbar's link popover (D-1). */
export const RICH_TEXT_ALLOWED_LINK_PROTOCOLS = ['http:', 'https:', 'mailto:'] as const
