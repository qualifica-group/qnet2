import { useEffect, useMemo } from 'react'
import { EditorContent, useEditor } from '@tiptap/react'
import { cn } from '@/lib/utils'
import { createRichTextExtensions, RichTextReadOnlyMention } from '@/components/rich-text/rich-text-extensions'
import { RICH_TEXT_PROSE_CLASS } from '@/components/rich-text/rich-text-prose'
import { RichTextMentionRendererProvider } from '@/components/rich-text/rich-text-mention-renderer-context'
import type { RichTextMentionRenderer } from '@/components/rich-text/rich-text-mention-renderer-context'

export interface RichTextContentProps {
  /** Sanitized HTML fragment (RichTextHtml, D-1). `null`/empty renders nothing — the caller owns the empty state. */
  html: string | null
  /** Renders a mention node (D-7, notes only); omit for the plain `@Label` fallback. */
  mentionRenderer?: RichTextMentionRenderer
  className?: string
}

/**
 * Read-only rendering of a rich text field (D-11). Never
 * `dangerouslySetInnerHTML` (react-security.md): the HTML is parsed through
 * Tiptap's own schema (same allow-list as `RichTextEditor`) and rendered as
 * real DOM nodes — an element outside the schema (e.g. `<script>`) is simply
 * not part of it, so it can never reach the page as a tag.
 */
export function RichTextContent({ html, mentionRenderer, className }: RichTextContentProps) {
  const extensions = useMemo(
    () => createRichTextExtensions({ extraExtensions: [RichTextReadOnlyMention] }),
    [],
  )

  const editor = useEditor({
    extensions,
    content: html ?? '',
    editable: false,
  })

  useEffect(() => {
    if (!editor) {
      return
    }
    editor.commands.setContent(html ?? '', { emitUpdate: false })
  }, [editor, html])

  if (!html) {
    return null
  }

  return (
    <RichTextMentionRendererProvider value={mentionRenderer}>
      <EditorContent editor={editor} className={cn('text-sm', RICH_TEXT_PROSE_CLASS, className)} />
    </RichTextMentionRendererProvider>
  )
}
