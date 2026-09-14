/**
 * TanStack Query mutations backing the time entry form (spec 0122 MT-F2):
 * create/update/delete, one invalidation point (`timeEntryKeys.all` covers
 * list/overview/pulse/team/detail/task in a single call) and the success
 * toast. 422 handling is NOT here: `use-time-entry-form.ts` awaits
 * `mutateAsync` and maps the rejection onto form fields, so these hooks stay
 * silent on error and let that catch run.
 */

import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { createTimeEntry, deleteTimeEntry, updateTimeEntry } from '@/features/time-entries/api'
import { timeEntryKeys } from '@/features/time-entries/query-keys'
import type {
  CreateTimeEntryPayload,
  TimeEntry,
  UpdateTimeEntryPayload,
} from '@/features/time-entries/types'

/** Creates a time entry (`POST /api/time-entries`). */
export function useCreateTimeEntry() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: CreateTimeEntryPayload) => createTimeEntry(payload),
    onSuccess: async (entry: TimeEntry) => {
      queryClient.setQueryData(timeEntryKeys.detail(entry.id), entry)
      await queryClient.invalidateQueries({ queryKey: timeEntryKeys.all })
      toast.success(t('timeEntries.form.created'))
    },
  })
}

interface UpdateTimeEntryArgs {
  id: number
  payload: UpdateTimeEntryPayload
}

/** Updates a time entry (`PUT /api/time-entries/{id}`). */
export function useUpdateTimeEntry() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, payload }: UpdateTimeEntryArgs) => updateTimeEntry(id, payload),
    onSuccess: async (entry: TimeEntry) => {
      queryClient.setQueryData(timeEntryKeys.detail(entry.id), entry)
      await queryClient.invalidateQueries({ queryKey: timeEntryKeys.all })
      toast.success(t('timeEntries.form.updated'))
    },
  })
}

/**
 * Deletes a time entry (`DELETE /api/time-entries/{id}`). Unlike create/update
 * this one owns both toasts: there is no form to catch the rejection and map
 * it onto a field, so `time-entry-form-actions.tsx` awaits `mutateAsync` only
 * to close the confirmation, the error toast fires from here.
 */
export function useDeleteTimeEntry() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => deleteTimeEntry(id),
    onSuccess: async (_data, id) => {
      queryClient.removeQueries({ queryKey: timeEntryKeys.detail(id) })
      await queryClient.invalidateQueries({ queryKey: timeEntryKeys.all })
      toast.success(t('timeEntries.table.deleted'))
    },
    onError: () => {
      toast.error(t('timeEntries.table.deleteFailed'))
    },
  })
}
