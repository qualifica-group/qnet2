import { createContext, useContext } from 'react'
import type { Poll, TaskRef } from '@/features/work-order-page/types'

/**
 * Data and actions the task/poll node views need. The panel provides it from
 * the page query, so a node view never fetches on its own (one GET per page,
 * spec 0420 D-6/D-7).
 */
export interface PageBlocksContextValue {
  tasks: Record<string, TaskRef>
  polls: Record<string, Poll>
  /** False on a read-only page (no `edit_page`, or work order completed/closed): voting is disabled. */
  canVote: boolean
  /** Poll with a vote/retract request in flight, to disable its options meanwhile. */
  pendingPollId: number | null
  onOpenTask: (taskId: number) => void
  onVote: (pollId: number, optionKey: string) => void
  onRetractVote: (pollId: number) => void
}

const NOOP = () => undefined

export const PageBlocksContext = createContext<PageBlocksContextValue>({
  tasks: {},
  polls: {},
  canVote: false,
  pendingPollId: null,
  onOpenTask: NOOP,
  onVote: NOOP,
  onRetractVote: NOOP,
})

export function usePageBlocks(): PageBlocksContextValue {
  return useContext(PageBlocksContext)
}
