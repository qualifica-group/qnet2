import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Lock, LockOpen } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useConfirm } from '@/components/confirm-dialog-context'
import { ACTION_BUTTON_VARIANT } from '@/features/table/action-tone'
import { REOPEN_PAYLOAD, useWorkOrderClosure } from '@/features/work-orders/use-work-order-closure'
import { WorkOrderForceCloseDialog } from '@/features/work-orders/work-order-force-close-dialog'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

interface WorkOrderClosureActionsProps {
  workOrder: WorkOrderDetailWithPermissions
  /** After the closure state changed, so the host refreshes whatever lists the work order. */
  onChanged?: () => void
}

/**
 * The record's closure action (user directive 2026-10-06: an action, not a
 * field): "Chiusura forzata" on an open commessa, opening the reason dialog,
 * or "Riapri" on a force-closed one, after a confirmation. Each is shown only
 * when the server grants it (`permissions.actions.force_close`/`reopen`).
 */
export function WorkOrderClosureActions({ workOrder, onChanged }: WorkOrderClosureActionsProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const [dialogWorkOrderId, setDialogWorkOrderId] = useState<number | null>(null)
  const { actions } = workOrder.permissions
  const reopen = useWorkOrderClosure(() => {
    toast.success(t('workOrders.actions.reopen.success'))
    onChanged?.()
  })

  const handleReopen = async () => {
    const confirmed = await confirm({
      title: t('workOrders.actions.reopen.title'),
      description: t('workOrders.actions.reopen.description'),
      confirmLabel: t('workOrders.actions.reopen.confirm'),
      tone: 'warning',
    })
    if (confirmed) {
      reopen.mutate(
        { workOrderId: workOrder.id, payload: REOPEN_PAYLOAD },
        { onError: () => toast.error(t('workOrders.form.genericError')) },
      )
    }
  }

  return (
    <>
      {actions.force_close ? (
        <Button
          type="button"
          variant={ACTION_BUTTON_VARIANT.danger}
          size="sm"
          onClick={() => setDialogWorkOrderId(workOrder.id)}
        >
          <Lock aria-hidden="true" />
          {t('actions.forceClose')}
        </Button>
      ) : null}
      {actions.reopen ? (
        <Button
          type="button"
          variant={ACTION_BUTTON_VARIANT.action}
          className="bg-card"
          size="sm"
          onClick={() => void handleReopen()}
          disabled={reopen.isPending}
        >
          <LockOpen aria-hidden="true" />
          {t('actions.reopen')}
        </Button>
      ) : null}
      <WorkOrderForceCloseDialog
        workOrderId={dialogWorkOrderId}
        onOpenChange={(open) => {
          if (!open) {
            setDialogWorkOrderId(null)
          }
        }}
        openTasksCount={workOrder.open_tasks_count}
        onClosed={onChanged}
      />
    </>
  )
}
