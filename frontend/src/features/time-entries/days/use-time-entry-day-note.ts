/**
 * Owns the day note's save-on-blur mutation (spec 0122 AC-036): `PUT
 * /api/time-entries/day-notes`, skipped when the trimmed value did not
 * change, invalidating the whole module on success so the day list (and any
 * other consumer) picks up the new/cleared note.
 */

import { useCallback } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { saveTimeEntryDayNote } from '@/features/time-entries/api'
import { timeEntryKeys } from '@/features/time-entries/query-keys'

interface UseTimeEntryDayNoteArgs {
  date: string
  /** Current, persisted note value (empty string when there is none). */
  note: string
  /** The dashboard's selected user (D-8: `manageAll` writes someone else's note). */
  selectedUserId?: number
}

export interface UseTimeEntryDayNoteResult {
  /** Commits `nextValue` if (and only if) it differs from the persisted note, trimmed. */
  save: (nextValue: string) => void
  isSaving: boolean
}

export function useTimeEntryDayNote({
  date,
  note,
  selectedUserId,
}: UseTimeEntryDayNoteArgs): UseTimeEntryDayNoteResult {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const mutation = useMutation({
    mutationFn: (nextNote: string) =>
      saveTimeEntryDayNote({ user_id: selectedUserId, date, note: nextNote }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: timeEntryKeys.all })
    },
    onError: () => {
      toast.error(t('timeEntries.dayCard.noteSaveError'))
    },
  })

  const save = useCallback(
    (nextValue: string) => {
      if (nextValue.trim() === note.trim()) {
        return
      }
      mutation.mutate(nextValue)
    },
    [mutation, note],
  )

  return { save, isSaving: mutation.isPending }
}
