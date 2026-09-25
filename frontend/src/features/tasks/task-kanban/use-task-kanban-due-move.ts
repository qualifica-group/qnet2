/**
 * Resolves a "per scadenza" Kanban drop (spec 0157 D-2): a drop onto a valid
 * bucket writes the bucket's own `end_date` (see `dueBucketDropDate`) via the
 * SAME `PATCH /api/tasks/{id}` the list's cell edit and the commessa board
 * already use.
 */
import { useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { updateTask } from '@/features/tasks/api'
import { actionErrorMessage } from '@/features/tasks/task-action-error-message'
import { dueBucketDropDate, type DueBucketKey } from '@/features/tasks/task-kanban/task-kanban-due-buckets'
import type { TaskKanbanRow } from '@/features/tasks/task-kanban/task-kanban-types'

export interface UseTaskKanbanDueMoveArgs {
  today: string
  /** Called after a successful PATCH so the caller refetches the board. */
  onMutated: () => void
}

export function useTaskKanbanDueMove({ today, onMutated }: UseTaskKanbanDueMoveArgs) {
  const { t } = useTranslation()

  const moveToBucket = useCallback(
    (row: TaskKanbanRow, targetKey: DueBucketKey) => {
      const endDate = dueBucketDropDate(targetKey, today)
      if (endDate === null) {
        return
      }
      updateTask(Number(row.id), { end_date: endDate })
        .then(onMutated)
        .catch((error: unknown) => toast.error(actionErrorMessage(t, error)))
    },
    [today, t, onMutated],
  )

  return { moveToBucket }
}
