import { useCallback } from 'react'
import { RichTextContent } from '@/components/rich-text/rich-text-content'
import { MentionBadge } from '@/features/notes/mention-badge'
import type { NoteMention } from '@/features/notes/types'

/** Hoisted so an omitted `mentions` never rebuilds the lookup on every render. */
const NO_MENTIONS: NoteMention[] = []

export interface NoteBodyProps {
  /** Sanitized HTML fragment (RichTextHtml, D-1), mention nodes per D-7. */
  body: string
  /**
   * The note's resolved mentions, the only source of a mentioned user's
   * avatar: the mention node only carries `{id, label}`, so without this a
   * chip would show initials for someone who has a photo everywhere else.
   */
  mentions?: NoteMention[]
}

/**
 * Renders a note's sanitized body (D-11 `RichTextContent`): mention nodes
 * become `MentionBadge` chips, everything else is the shared read-only rich
 * text rendering — never `dangerouslySetInnerHTML` (react-security.md), the
 * HTML is parsed through Tiptap's own schema instead.
 */
export function NoteBody({ body, mentions = NO_MENTIONS }: NoteBodyProps) {
  const renderMention = useCallback(
    ({ userId, label }: { userId: number; label: string }) => {
      const avatarUrl = mentions.find((mention) => mention.id === userId)?.avatar_url ?? null
      return <MentionBadge userId={userId} name={label} avatarUrl={avatarUrl} />
    },
    [mentions],
  )

  return <RichTextContent html={body} mentionRenderer={renderMention} className="text-foreground" />
}
