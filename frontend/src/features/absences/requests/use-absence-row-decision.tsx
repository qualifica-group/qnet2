import { useCallback, useState, type ReactNode } from 'react'
import { useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { approveAbsenceRequest, rejectAbsenceRequest } from '@/features/absences/api'
import { AbsenceRejectDialog } from '@/features/absences/requests/absence-reject-dialog'
import { serverMessage } from '@/features/absences/requests/absence-server-errors'
import { useAbsenceRefresh } from '@/features/absences/requests/use-absence-refresh'
import type { RowActionHandler } from '@/features/table/row-actions'

/**
 * Row actions of the grid: `approve` (the table already asked for the
 * confirmation, `confirm: true` in the catalogue) posts straight away, `reject`
 * opens the reason dialog, anything else (`view`) opens the detail.
 */
export function useAbsenceRowDecision(onOpen: (id: number) => void, refreshGrid: () => void) {
  const { t } = useTranslation()
  const refresh = useAbsenceRefresh(refreshGrid)
  const [rejectingId, setRejectingId] = useState<number | null>(null)

  const approve = useMutation({
    mutationFn: (id: number) => approveAbsenceRequest(id),
    onSuccess: (_request, id) => {
      refresh(id)
      toast.success(t('absences.requests.actions.approved'))
    },
    onError: (error) => toast.error(serverMessage(error) ?? t('absences.requests.actions.error')),
  })
  const reject = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) =>
      rejectAbsenceRequest(id, { rejection_reason: reason }),
    onSuccess: (_request, { id }) => {
      refresh(id)
      toast.success(t('absences.requests.actions.rejected'))
    },
  })

  const handleAction: RowActionHandler = useCallback(
    (action, row) => {
      const id = Number(row.id)
      if (action.key === 'approve') approve.mutate(id)
      else if (action.key === 'reject') setRejectingId(id)
      else onOpen(id)
    },
    [approve, onOpen],
  )

  // Mounted only while open so the reason field starts empty every time
  const dialogSlot: ReactNode =
    rejectingId === null ? null : (
      <AbsenceRejectDialog
        open
        onOpenChange={(open) => {
          if (!open) setRejectingId(null)
        }}
        onReject={(reason) => reject.mutateAsync({ id: rejectingId, reason })}
        isPending={reject.isPending}
      />
    )

  return { handleAction, dialogSlot }
}
