/**
 * Shared `components/rich-text/` UI (spec 0128 D-11): toolbar, link popover
 * and read-only content states. Satellite file so `en.ts` stays within the
 * size limits (engineering.md §6).
 */

export const richText = {
  toolbar: {
    bold: 'Bold',
    italic: 'Italic',
    underline: 'Underline',
    strike: 'Strikethrough',
    bulletList: 'Bullet list',
    orderedList: 'Numbered list',
    heading2: 'Heading 2',
    heading3: 'Heading 3',
    blockquote: 'Quote',
    codeBlock: 'Code block',
    link: 'Link',
    image: 'Image',
  },
  link: {
    urlLabel: 'URL',
    urlPlaceholder: 'https://…',
    apply: 'Apply',
    cancel: 'Cancel',
    invalidUrl: 'Invalid URL. Use an http, https or mailto link.',
  },
  errors: {
    invalidImageType: 'Unsupported image format. Use PNG, JPEG, GIF or WEBP.',
    imageTooLarge: 'The image exceeds the maximum size of {{size}} MB.',
    tooManyImages: "You've reached the maximum number of images for this field ({{max}}).",
    imageReadFailed: 'Could not read the image. Please try again.',
  },
  content: {
    imageLoading: 'Loading image…',
    imageUnavailable: 'Image unavailable',
  },
}
