/**
 * TanStack Query mutation backing the Task-embedded "Nuovo intervallo"
 * editor (spec 0122 MT-F6, D-9): creates via the task-scoped endpoint and
 * invalidates `timeEntryKeys.all` — one call covers the task's own entry
 * list AND the standalone dashboard/day lists, which read the same rows.
 */

import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { createTaskTimeEntry } from '@/features/time-entries/api'
import { timeEntryKeys } from '@/features/time-entries/query-keys'
import type { CreateTaskTimeEntryPayload, TimeEntry } from '@/features/time-entries/types'

export function useCreateTaskTimeEntry(taskId: number) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: CreateTaskTimeEntryPayload) => createTaskTimeEntry(taskId, payload),
    onSuccess: async (entry: TimeEntry) => {
      queryClient.setQueryData(timeEntryKeys.detail(entry.id), entry)
      await queryClient.invalidateQueries({ queryKey: timeEntryKeys.all })
      toast.success(t('timeEntries.form.created'))
    },
  })
}
