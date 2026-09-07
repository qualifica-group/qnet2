import { useCallback, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation } from '@tanstack/react-query'
import { toast } from 'sonner'
import { useAbilities } from '@/features/auth/use-abilities'
import { assignRequestManagerGa3 } from '@/features/request-management/api'
import { GA3_MANAGER_POSITION } from '@/features/request-management/types'
import { useActiveCategoryManagerLabels } from '@/features/request-management/use-active-category-manager-labels'

interface UseRequestManagerGa3AssignmentOptions {
  /** The active category tab, the source of the slot's own name (spec 0080). */
  categoryId: number | null
  /** Run after a successful write: refresh the grid and drop the selection. */
  onAssigned: () => void
}

/**
 * The bulk GA3 assignment flow (spec 0104): permission gate, resolved slot
 * label, dialog state, mutation and feedback. Its own hook rather than more
 * state in `RequestManagementTable` — that adapter is at the file-size limit,
 * and this is a self-contained flow whose only contact points with the table
 * are the "Azioni" entry and the popup it renders.
 *
 * The Sede is absent on purpose: only the GA2 Operatore slot is bound to one
 * (D-1), so unlike the operators flow this collects a single user — or `null`,
 * which clears the slot on the whole selection (D-2).
 */
export function useRequestManagerGa3Assignment({
  categoryId,
  onAssigned,
}: UseRequestManagerGa3AssignmentOptions) {
  const { t } = useTranslation()
  const { can } = useAbilities()

  const [open, setOpen] = useState(false)
  const [ids, setIds] = useState<number[]>([])

  // Its OWN ability on top of `update` (D-3): a bulk write resolves no
  // per-field permission, so restricting the `manager_ga3_id` field alone
  // would leave this action as the way around that restriction.
  const canAssign = can('request-management.update') && can('request-management.assignManagerGa3')

  // The slot's name comes from the SAME source that relabels the grid column
  // (spec 0080, extended to GA3): the active tab's category labels, keyed by
  // pivot position. "Tutte", or a category defining no label for the position,
  // falls back to the column's own i18n key.
  const { data: managerLabels } = useActiveCategoryManagerLabels(categoryId)
  const label =
    managerLabels?.[String(GA3_MANAGER_POSITION)] ?? t('requestManagement.columns.managerGa3')

  const mutation = useMutation({
    mutationFn: assignRequestManagerGa3,
    onSuccess: (result) => {
      toast.success(t('requestManagement.assignManagerGa3.success', { count: result.assigned }))
      onAssigned()
    },
  })

  const assign = useCallback(
    async (userId: number | null) => {
      try {
        await mutation.mutateAsync({ request_ids: ids, manager_ga3_id: userId })
      } catch (error) {
        toast.error(t('requestManagement.assignManagerGa3.errors.generic'))
        throw error
      }
    },
    [mutation, ids, t],
  )

  const openDialog = useCallback((selectedIds: number[]) => {
    setIds(selectedIds)
    setOpen(true)
  }, [])

  return { canAssign, label, open, setOpen, selectionCount: ids.length, openDialog, assign }
}
