import { RecordTabCount } from '@/components/detail/record-tab-icon'
import type { NoteQuoteScope } from '@/features/notes/types'
import { useNotes } from '@/features/notes/use-notes'

interface NotesTabCountProps {
  entityType: string
  entityId: number
  /** The scope the tab's `NotesSection` opens with (`lockedQuoteId` on the Offerta), so the two share one request. */
  quoteScope?: NoteQuoteScope
}

/**
 * Count of a Note tab: the notes the user has not read yet (`meta.unread`,
 * same rule as the grids' badge). Reads the first page of the same feed
 * `NotesSection` shows, which refetches it once the thread is marked read.
 */
export function NotesTabCount({ entityType, entityId, quoteScope = 'all' }: NotesTabCountProps) {
  const { data } = useNotes(entityType, entityId, true, quoteScope)

  return <RecordTabCount count={data?.pages[0]?.meta.unread} />
}
