import { useCallback } from 'react'
import { useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { UserCheck, UserX } from 'lucide-react'
import { useConfirm } from '@/components/confirm-dialog-context'
import { bulkSetUsersActive } from '@/features/users/api'
import { backendErrorMessage } from '@/features/users/user-action-errors'
import type { UserBulkActivationResult } from '@/features/users/types'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'

/** Prefix of the bulk keys, next to the single-user record actions they mirror. */
const BULK_KEYS = 'users.detail.actions.bulk'

interface UseUserBulkActivationArgs {
  /** Refreshes the grid and whatever else lists the users. */
  onChanged: () => void
  /** Empties the grid selection once the call went through. */
  clearSelection: () => void
}

/**
 * "Activate selected" / "Deactivate selected" of the users grid (spec 0310):
 * one `bulk-activation` call, a mixed outcome reported in one toast. Like the
 * detail, deactivating asks for a confirmation and activating does not. The
 * backend re-authorizes every user.
 */
export function useUserBulkActivation({ onChanged, clearSelection }: UseUserBulkActivationArgs) {
  const { t } = useTranslation()
  const confirm = useConfirm()

  const report = useCallback(
    (result: UserBulkActivationResult) => {
      const updated = result.updated.length
      if (result.failed.length === 0) {
        toast.success(t(`${BULK_KEYS}.allUpdated`, { count: updated }))
        return
      }
      toast.warning(t(`${BULK_KEYS}.result`, { updated, failed: result.failed.length }), {
        description: result.failed
          .map((item) => t(`${BULK_KEYS}.failedItem`, { name: item.name ?? `#${item.id}`, message: item.message }))
          .join(' '),
      })
    },
    [t],
  )

  const mutation = useMutation({
    mutationFn: bulkSetUsersActive,
    onSuccess: (result) => {
      report(result)
      onChanged()
      clearSelection()
    },
    onError: (error) => toast.error(backendErrorMessage(error) ?? t(`${BULK_KEYS}.error`)),
  })

  const deactivate = useCallback(
    async (ids: number[]) => {
      const confirmed = await confirm({
        tone: 'warning',
        title: t(`${BULK_KEYS}.deactivateTitle`, { count: ids.length }),
        description: t('users.detail.actions.deactivateConfirm.description'),
        confirmLabel: t('users.detail.actions.deactivate'),
      })
      if (confirmed) {
        mutation.mutate({ is_active: false, ids })
      }
    },
    [confirm, mutation, t],
  )

  const getBulkActions = useCallback(
    (selection: TableSelection): BulkAction[] => {
      const ids = selection.ids.map(Number)
      return [
        {
          key: 'activate',
          label: t(`${BULK_KEYS}.activate`),
          icon: UserCheck,
          disabled: mutation.isPending,
          onSelect: () => mutation.mutate({ is_active: true, ids }),
        },
        {
          key: 'deactivate',
          label: t(`${BULK_KEYS}.deactivate`),
          icon: UserX,
          disabled: mutation.isPending,
          onSelect: () => void deactivate(ids),
        },
      ]
    },
    [deactivate, mutation, t],
  )

  return { getBulkActions }
}
