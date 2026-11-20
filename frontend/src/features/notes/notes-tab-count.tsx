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
 * Count of a Note tab (`meta.total`: roots plus replies). Reads the first page
 * of the same feed `NotesSection` shows, so a note written or deleted there
 * updates it.
 */
export function NotesTabCount({ entityType, entityId, quoteScope = 'all' }: NotesTabCountProps) {
  const { data } = useNotes(entityType, entityId, true, quoteScope)

  return <RecordTabCount count={data?.pages[0]?.meta.total} />
}
