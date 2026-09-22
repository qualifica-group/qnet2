/**
 * Owns which bulk-action dialog is open (D-7): a single `activeAction` slot,
 * since only one of the six dialogs can be open at a time from the bulk bar.
 * The mutation itself lives in each dialog (`useBulkBoardTaskAction`), not
 * here — every dialog owns its own submit/error handling for its own fields
 * (mirrors `TaskCompleteDialog`'s pattern), this hook only tracks the toggle.
 */

import { useState } from 'react'
import type { BulkTaskAction } from '@/features/work-orders/task-board/types'

export function useTaskBoardBulkBar() {
  const [activeAction, setActiveAction] = useState<BulkTaskAction | null>(null)

  return {
    activeAction,
    openDialog: setActiveAction,
    closeDialog: () => setActiveAction(null),
  }
}
