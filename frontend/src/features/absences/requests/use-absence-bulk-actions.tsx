import { useCallback, useState, type ReactNode } from 'react'
import { useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Check, X } from 'lucide-react'
import { useConfirm } from '@/components/confirm-dialog-context'
import { bulkDecideAbsenceRequests } from '@/features/absences/api'
import { AbsenceRejectDialog } from '@/features/absences/requests/absence-reject-dialog'
import { serverMessage } from '@/features/absences/requests/absence-server-errors'
import { useAbsenceRefresh } from '@/features/absences/requests/use-absence-refresh'
import type { AbsenceBulkDecisionResult } from '@/features/absences/types'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'

interface UseAbsenceBulkActionsArgs {
  /** Purges and reloads the grid. */
  refreshGrid: () => void
  /** Empties the grid selection once the decision went through. */
  clearSelection: () => void
}

/**
 * "Approve selected" / "Reject selected" of the To approve tab (spec 0304 D-17):
 * one `bulk-decision` call, a mixed outcome reported in one toast. Approving
 * asks for a confirmation, rejecting for the single shared reason.
 */
export function useAbsenceBulkActions({ refreshGrid, clearSelection }: UseAbsenceBulkActionsArgs) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const refresh = useAbsenceRefresh(refreshGrid)
  const [rejectIds, setRejectIds] = useState<number[] | null>(null)

  const report = useCallback(
    (result: AbsenceBulkDecisionResult) => {
      if (result.failed.length === 0) {
        toast.success(t('absences.bulk.allDecided', { decided: result.decided.length }))
        return
      }
      toast.warning(t('absences.bulk.result', { decided: result.decided.length, failed: result.failed.length }), {
        description: result.failed.map((item) => t('absences.bulk.failedItem', item)).join(' '),
      })
    },
    [t],
  )

  const decide = useMutation({
    mutationFn: bulkDecideAbsenceRequests,
    onSuccess: (result, payload) => {
      report(result)
      refresh(payload.ids)
      clearSelection()
    },
  })

  const approve = useCallback(
    async (ids: number[]) => {
      const confirmed = await confirm({
        title: t('absences.bulk.approveTitle', { count: ids.length }),
        description: t('absences.bulk.approveDescription'),
        confirmLabel: t('absences.bulk.approveConfirm'),
      })
      if (!confirmed) return
      try {
        await decide.mutateAsync({ action: 'approve', ids })
      } catch (error) {
        toast.error(serverMessage(error) ?? t('absences.bulk.error'))
      }
    },
    [confirm, decide, t],
  )

  const getBulkActions = useCallback(
    (selection: TableSelection): BulkAction[] => {
      const ids = selection.ids.map(Number)
      if (ids.length === 0) return []
      return [
        { key: 'approve', label: t('absences.bulk.approve'), icon: Check, onSelect: () => void approve(ids) },
        { key: 'reject', label: t('absences.bulk.reject'), icon: X, destructive: true, onSelect: () => setRejectIds(ids) },
      ]
    },
    [approve, t],
  )

  // Mounted only while open so the reason field starts empty every time
  const dialogSlot: ReactNode =
    rejectIds === null ? null : (
      <AbsenceRejectDialog
        open
        onOpenChange={(open) => {
          if (!open) setRejectIds(null)
        }}
        onReject={(reason) => decide.mutateAsync({ action: 'reject', ids: rejectIds, rejection_reason: reason })}
        isPending={decide.isPending}
        title={t('absences.bulk.rejectTitle', { count: rejectIds.length })}
        description={t('absences.bulk.rejectDescription')}
      />
    )

  return { getBulkActions, dialogSlot }
}
