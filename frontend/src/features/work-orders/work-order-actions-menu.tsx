import { useTranslation } from 'react-i18next'
import { MoreVertical } from 'lucide-react'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Button } from '@/components/ui/button'
import { ProformaRequestDialog } from '@/features/work-orders/proforma-request-dialog'
import { WorkOrderForceCloseDialog } from '@/features/work-orders/work-order-force-close-dialog'
import { WorkOrderProgressDialog } from '@/features/work-orders/work-order-progress-dialog'
import { WorkOrderRequestUpdateDialog } from '@/features/work-orders/work-order-request-update-dialog'
import { useWorkOrderActionsMenu, type WorkOrderMenuAction } from '@/features/work-orders/use-work-order-actions-menu'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

interface WorkOrderActionsMenuProps {
  workOrder: WorkOrderDetailWithPermissions
  /** After an action changed the record (spec 0195 D-7: the host refreshes its lists). */
  onChanged?: () => void
  /** After "Elimina": the host leaves the record. Absent, no delete is offered. */
  onDeleted?: () => void
}

function MenuItems({ items }: { items: WorkOrderMenuAction[] }) {
  return items.map((item) => (
    <DropdownMenuItem key={item.key} variant={item.variant} disabled={item.disabled} onSelect={item.onSelect}>
      <item.icon aria-hidden="true" />
      {item.label}
    </DropdownMenuItem>
  ))
}

/**
 * The work order's record actions folded into a "..." menu at the top right
 * of the identity band, like the legacy "Gestione commessa" (user directive
 * 2026-11-27): Avanzamento, Copia dati dall'offerta, Richiedi aggiornamento,
 * Richiedi proforma, then Chiusura forzata / Riapri and Elimina. The popups
 * are mounted beside the menu, since its content unmounts when it closes.
 */
export function WorkOrderActionsMenu({ workOrder, onChanged, onDeleted }: WorkOrderActionsMenuProps) {
  const { t } = useTranslation()
  const { items, dialog, closeDialog } = useWorkOrderActionsMenu({ workOrder, onChanged, onDeleted })
  const workItems = items.filter((item) => item.section === 'work')
  const closureItems = items.filter((item) => item.section === 'closure')
  const handleOpenChange = (open: boolean) => {
    if (!open) {
      closeDialog()
    }
  }

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            aria-label={t('workOrders.detail.actionsMenu')}
            title={t('workOrders.detail.actionsMenu')}
          >
            <MoreVertical aria-hidden="true" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <MenuItems items={workItems} />
          {workItems.length > 0 && closureItems.length > 0 ? <DropdownMenuSeparator /> : null}
          <MenuItems items={closureItems} />
        </DropdownMenuContent>
      </DropdownMenu>

      <WorkOrderProgressDialog
        workOrderId={dialog === 'progress' ? workOrder.id : null}
        onOpenChange={handleOpenChange}
        onMutated={onChanged}
      />
      {dialog === 'requestUpdate' ? (
        <WorkOrderRequestUpdateDialog open onOpenChange={handleOpenChange} workOrder={workOrder} />
      ) : null}
      {dialog === 'proforma' ? (
        <ProformaRequestDialog
          workOrderId={workOrder.id}
          workOrderCode={workOrder.code}
          open
          onOpenChange={handleOpenChange}
          onSent={() => {
            closeDialog()
            onChanged?.()
          }}
        />
      ) : null}
      <WorkOrderForceCloseDialog
        workOrderId={dialog === 'forceClose' ? workOrder.id : null}
        onOpenChange={handleOpenChange}
        openTasksCount={workOrder.open_tasks_count}
        onClosed={onChanged}
      />
    </>
  )
}
