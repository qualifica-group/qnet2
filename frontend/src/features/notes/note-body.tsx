import { Fragment } from 'react'
import { MentionBadge } from '@/features/notes/mention-badge'
import { splitIntoSegments } from '@/features/notes/mention-tokens'
import type { NoteMention } from '@/features/notes/types'

/** Hoisted so an omitted `mentions` never rebuilds the lookup on every render. */
const NO_MENTIONS: NoteMention[] = []

export interface NoteBodyProps {
  /** Raw note body, with mention tokens still inline (D-12). */
  body: string
  /**
   * The note's resolved mentions, the only source of a mentioned user's
   * avatar: the body token carries just `{name, id}`, so without this a chip
   * would show initials for someone who has a photo everywhere else.
   */
  mentions?: NoteMention[]
}

/**
 * Renders a note's raw body as safe React nodes: plain text runs plus
 * highlighted mention chips. Security-critical (react-security.md): never
 * `dangerouslySetInnerHTML` on this untrusted, user-authored text — the body
 * is split into segments and each one rendered as a plain text node or a
 * `<span>` chip, so React's own escaping applies throughout.
 */
export function NoteBody({ body, mentions = NO_MENTIONS }: NoteBodyProps) {
  const segments = splitIntoSegments(body)
  const avatarByUserId = new Map(mentions.map((mention) => [mention.id, mention.avatar_url]))

  return (
    <p className="text-sm break-words whitespace-pre-wrap text-foreground">
      {segments.map((segment) =>
        segment.type === 'mention' && segment.userId !== undefined ? (
          <MentionBadge
            key={segment.key}
            userId={segment.userId}
            name={segment.content}
            avatarUrl={avatarByUserId.get(segment.userId) ?? null}
          />
        ) : (
          <Fragment key={segment.key}>{segment.content}</Fragment>
        ),
      )}
    </p>
  )
}
