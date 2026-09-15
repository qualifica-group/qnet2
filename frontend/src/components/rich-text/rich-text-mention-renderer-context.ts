import { createContext, useContext } from 'react'
import type { ReactNode } from 'react'

export interface RichTextMentionRendererProps {
  userId: number
  label: string
}

export type RichTextMentionRenderer = (props: RichTextMentionRendererProps) => ReactNode

/**
 * Carries `RichTextContent`'s `mentionRenderer` prop down to the mention
 * node view, which Tiptap renders through a portal outside the normal props
 * chain — React context still reaches it because the portal stays inside the
 * same React tree, only its DOM target differs.
 */
const RichTextMentionRendererContext = createContext<RichTextMentionRenderer | undefined>(undefined)

export const RichTextMentionRendererProvider = RichTextMentionRendererContext.Provider

export function useRichTextMentionRenderer(): RichTextMentionRenderer | undefined {
  return useContext(RichTextMentionRendererContext)
}
