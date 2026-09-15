import { NodeViewWrapper } from '@tiptap/react'
import type { NodeViewProps } from '@tiptap/react'
import { useRichTextMentionRenderer } from '@/components/rich-text/rich-text-mention-renderer-context'

/**
 * Node view for `RichTextReadOnlyMention` (`rich-text-extensions.ts`): calls
 * the caller's `mentionRenderer` when provided, else falls back to the plain
 * `@Label` text the node's own `renderHTML` already produces server-side.
 */
export function RichTextMentionView({ node }: NodeViewProps) {
  const renderMention = useRichTextMentionRenderer()
  const id = typeof node.attrs.id === 'string' ? node.attrs.id : null
  const label = typeof node.attrs.label === 'string' ? node.attrs.label : (id ?? '')

  if (renderMention && id !== null) {
    return <NodeViewWrapper as="span">{renderMention({ userId: Number(id), label })}</NodeViewWrapper>
  }

  return <NodeViewWrapper as="span">{`@${label}`}</NodeViewWrapper>
}
