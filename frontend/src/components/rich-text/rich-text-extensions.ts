import { Image } from '@tiptap/extension-image'
import { Node } from '@tiptap/core'
import { Placeholder } from '@tiptap/extensions'
import { ReactNodeViewRenderer } from '@tiptap/react'
import { StarterKit } from '@tiptap/starter-kit'
import type { Extensions } from '@tiptap/core'
import { safeUrl } from '@/lib/safe-url'
import { RICH_TEXT_ALLOWED_LINK_PROTOCOLS } from '@/components/rich-text/rich-text-constants'
import { RichTextImageView } from '@/components/rich-text/rich-text-image-view'
import { RichTextMentionView } from '@/components/rich-text/rich-text-content-mention'

/**
 * Node type name of a mention (D-7). Shared constant so F2's `Mention`
 * extension configuration and `RichTextContent`'s read-only rendering agree
 * on the exact wire format without importing each other.
 */
export const RICH_TEXT_MENTION_NODE_NAME = 'mention'

interface MentionNodeLike {
  attrs: { id?: string | null; label?: string | null }
}

/**
 * `renderText`/`renderHTML` options for `@tiptap/extension-mention`'s
 * `Mention.configure(...)` (D-7 wire format: `span[data-type="mention"]
 * [data-id][data-label]`, text `@Label`). F2 passes these so the composer and
 * `RichTextContent`'s read-only rendering never drift apart on the markup the
 * sanitizer allow-list expects.
 */
export function richTextMentionRenderText({ node }: { node: MentionNodeLike }): string {
  const label = node.attrs.label ?? node.attrs.id ?? ''
  return `@${label}`
}

export function richTextMentionRenderHTML({
  node,
}: {
  node: MentionNodeLike
}): [string, Record<string, string>, string] {
  const id = node.attrs.id ?? ''
  const label = node.attrs.label ?? id
  return [
    'span',
    { 'data-type': RICH_TEXT_MENTION_NODE_NAME, 'data-id': id, 'data-label': label },
    `@${label}`,
  ]
}

/**
 * Custom image node (D-1/D-3). Only two shapes are ever recognized as this
 * node while parsing HTML: `img[data-attachment-id]` (already saved) and
 * `img[src^="data:"]` (freshly pasted/dropped/picked, not uploaded yet). A
 * remote `img[src="http…"]` matches neither parse rule, so it is silently
 * dropped by the DOM parser — the enforcement point for "no remote images",
 * both when typing and when pasting foreign HTML.
 */
export const RichTextImage = Image.extend({
  addAttributes() {
    return {
      alt: { default: null },
      src: {
        default: null,
        // Persisted images carry no `src` (D-3): only an unsaved `data:` URI
        // is ever written back out, so a saved image round-trips through
        // `getHTML()` without leaking a giant base64 string back onto the wire.
        renderHTML: (attributes: { src?: unknown }) =>
          typeof attributes.src === 'string' && attributes.src.startsWith('data:')
            ? { src: attributes.src }
            : {},
      },
      attachmentId: {
        default: null,
        parseHTML: (element: HTMLElement) => element.getAttribute('data-attachment-id'),
        renderHTML: (attributes: { attachmentId?: unknown }) =>
          attributes.attachmentId ? { 'data-attachment-id': String(attributes.attachmentId) } : {},
      },
    }
  },
  parseHTML() {
    return [{ tag: 'img[data-attachment-id]' }, { tag: 'img[src^="data:"]' }]
  },
  // The `![]()` markdown input rule would let a user insert an arbitrary
  // remote URL straight past the parse-rule allow-list above; out of scope
  // (constraints: images only via paste/drop/picker) and disabled here.
  addInputRules() {
    return []
  },
  // Shared by the editable editor and the read-only content viewer (D-11):
  // a saved image has no `src` on the wire, so both need to fetch its blob
  // through the authenticated client the same way.
  addNodeView() {
    return ReactNodeViewRenderer(RichTextImageView)
  },
}).configure({ inline: true, allowBase64: true })

/**
 * Read-only counterpart of F2's interactive `Mention` extension (D-7): same
 * wire format (`richTextMentionRenderHTML`), no suggestion/typing behavior —
 * `RichTextContent` only ever displays mentions, never authors them.
 */
export const RichTextReadOnlyMention = Node.create({
  name: RICH_TEXT_MENTION_NODE_NAME,
  group: 'inline',
  inline: true,
  atom: true,
  selectable: false,
  addAttributes() {
    return {
      id: { default: null, parseHTML: (element: HTMLElement) => element.getAttribute('data-id') },
      label: { default: null, parseHTML: (element: HTMLElement) => element.getAttribute('data-label') },
    }
  },
  parseHTML() {
    return [{ tag: `span[data-type="${RICH_TEXT_MENTION_NODE_NAME}"]` }]
  },
  renderHTML({ node }) {
    return richTextMentionRenderHTML({ node })
  },
  addNodeView() {
    return ReactNodeViewRenderer(RichTextMentionView)
  },
})

export interface CreateRichTextExtensionsOptions {
  /** i18n placeholder text; omit for no placeholder (e.g. read-only content). */
  placeholder?: string
  /**
   * Extra extensions layered on top of the shared base — F2 injects its
   * configured `Mention` extension here (D-7: notes only, never task/template
   * descriptions).
   */
  extraExtensions?: Extensions
}

/**
 * Shared Tiptap extension set for `RichTextEditor` and `RichTextContent`
 * (D-11), scoped to exactly the D-1 tag allow-list: paragraph, hard break,
 * bold/italic/underline/strike, bullet/ordered list, heading levels 2-3,
 * blockquote, code/code block, link (protocol allow-list), image. Mention is
 * deliberately NOT part of this base (D-7 scope) — see `extraExtensions`.
 */
export function createRichTextExtensions({
  placeholder,
  extraExtensions = [],
}: CreateRichTextExtensionsOptions = {}): Extensions {
  return [
    StarterKit.configure({
      heading: { levels: [2, 3] },
      horizontalRule: false,
      link: {
        openOnClick: false,
        HTMLAttributes: { rel: 'noopener noreferrer nofollow', target: '_blank' },
        // Authoritative scheme gate (D-1): governs manual `setLink`, autolink
        // detection and parsing pasted `<a href>` HTML alike.
        isAllowedUri: (url) => safeUrl(url, RICH_TEXT_ALLOWED_LINK_PROTOCOLS) !== undefined,
      },
    }),
    RichTextImage,
    ...(placeholder ? [Placeholder.configure({ placeholder })] : []),
    ...extraExtensions,
  ]
}
