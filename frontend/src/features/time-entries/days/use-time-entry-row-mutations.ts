/**
 * Row-level mutations of the day entries table (spec 0122 D-14): inline type
 * change and delete. Both PUT/DELETE `/api/time-entries/{id}` and invalidate
 * the whole module on success, so every open day card and the KPI/pulse
 * tiles (F5) pick up the change together.
 */

import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { deleteTimeEntry, updateTimeEntry } from '@/features/time-entries/api'
import { timeEntryKeys } from '@/features/time-entries/query-keys'
import type { TimeEntry, UpdateTimeEntryPayload } from '@/features/time-entries/types'

/** Rebuilds the full `PUT` payload from the current entry, D-14's "change type" contract. */
function toUpdatePayload(entry: TimeEntry, taskTypeId: number): UpdateTimeEntryPayload {
  return {
    date: entry.date,
    title: entry.title,
    task_type_id: taskTypeId,
    start_time: entry.start_time ?? undefined,
    end_time: entry.end_time ?? undefined,
    minutes: entry.minutes,
    notes: entry.notes,
    registry_id: entry.registry?.id ?? null,
    opportunity_id: entry.opportunity?.id ?? null,
    work_order_id: entry.work_order?.id ?? null,
    task_id: entry.task?.id ?? null,
  }
}

export interface UseTimeEntryRowMutationsResult {
  changeType: (entry: TimeEntry, taskTypeId: number) => void
  isChangingType: boolean
  deleteEntry: (entryId: number) => void
  isDeleting: boolean
}

export function useTimeEntryRowMutations(): UseTimeEntryRowMutationsResult {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const invalidate = () => queryClient.invalidateQueries({ queryKey: timeEntryKeys.all })

  const changeTypeMutation = useMutation({
    mutationFn: ({ entry, taskTypeId }: { entry: TimeEntry; taskTypeId: number }) =>
      updateTimeEntry(entry.id, toUpdatePayload(entry, taskTypeId)),
    onSuccess: () => {
      toast.success(t('timeEntries.table.updated'))
      void invalidate()
    },
    onError: () => {
      toast.error(t('timeEntries.table.updateFailed'))
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (entryId: number) => deleteTimeEntry(entryId),
    onSuccess: () => {
      toast.success(t('timeEntries.table.deleted'))
      void invalidate()
    },
    onError: () => {
      toast.error(t('timeEntries.table.deleteFailed'))
    },
  })

  return {
    changeType: (entry, taskTypeId) => {
      // D-14: a no-op re-selection of the same type never hits the API.
      if (taskTypeId === entry.task_type.id) {
        return
      }
      changeTypeMutation.mutate({ entry, taskTypeId })
    },
    isChangingType: changeTypeMutation.isPending,
    deleteEntry: (entryId) => deleteMutation.mutate(entryId),
    isDeleting: deleteMutation.isPending,
  }
}
