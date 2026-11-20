import { useTranslation } from 'react-i18next'
import { Trash2 } from 'lucide-react'
import { useConfirm } from '@/components/confirm-dialog-context'
import { Button } from '@/components/ui/button'
import { ACTION_BUTTON_VARIANT } from '@/features/table/action-tone'
import { useDeleteWorkOrder } from '@/features/work-orders/use-delete-work-order'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

interface WorkOrderDeleteActionProps {
  workOrder: WorkOrderDetailWithPermissions
  /** After the delete: the host leaves the record that no longer exists. */
  onDeleted: () => void
}

/**
 * "Elimina" in the record header (user request 2026-11-19): shown only when
 * the server grants `permissions.actions.delete`, which already excludes a
 * commessa with accounting documents (spec 0243); asks before deleting.
 */
export function WorkOrderDeleteAction({ workOrder, onDeleted }: WorkOrderDeleteActionProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const { remove, isPending } = useDeleteWorkOrder(onDeleted)

  if (!workOrder.permissions.actions.delete) {
    return null
  }

  const handleClick = async () => {
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('workOrders.actions.delete.confirmTitle'),
      description: t('workOrders.actions.delete.confirmDescription', { code: workOrder.code }),
      confirmLabel: t('workOrders.actions.delete.confirm'),
      cancelLabel: t('workOrders.actions.delete.cancel'),
    })
    if (confirmed) {
      await remove(workOrder.id)
    }
  }

  return (
    <Button
      type="button"
      variant={ACTION_BUTTON_VARIANT.danger}
      size="sm"
      disabled={isPending}
      onClick={() => void handleClick()}
    >
      <Trash2 aria-hidden="true" />
      {t('workOrders.actions.delete.label')}
    </Button>
  )
}
