import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { ClipboardCopy, Contact, Euro, Lock, LockOpen, MessageSquareMore, Trash2, TrendingUp, type LucideIcon } from 'lucide-react'
import { useConfirm } from '@/components/confirm-dialog-context'
import { useDeleteWorkOrder } from '@/features/work-orders/use-delete-work-order'
import { REOPEN_PAYLOAD, useWorkOrderClosure } from '@/features/work-orders/use-work-order-closure'
import { useWorkOrderCopyQuoteAttributes } from '@/features/work-orders/use-work-order-copy-quote-attributes'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/** The popups an action of the menu opens; they live outside the menu, which unmounts on close. */
export type WorkOrderActionDialog = 'progress' | 'team' | 'requestUpdate' | 'proforma' | 'forceClose'

/** `work`: the day-to-day actions; `closure`: closing, reopening and deleting, set apart below a separator. */
export type WorkOrderActionSection = 'work' | 'closure'

export interface WorkOrderMenuAction {
  key: string
  label: string
  icon: LucideIcon
  section: WorkOrderActionSection
  variant: 'default' | 'destructive'
  disabled: boolean
  onSelect: () => void
}

interface UseWorkOrderActionsMenuOptions {
  workOrder: WorkOrderDetailWithPermissions
  /** After an action changed the record, so the host refreshes whatever lists the work order. */
  onChanged?: () => void
  /** After "Elimina": the host leaves the record. Absent, no delete is offered. */
  onDeleted?: () => void
}

interface MenuActionSpec extends WorkOrderMenuAction {
  visible: boolean
}

const NOOP = () => {}

/**
 * The record actions of the work order's "..." menu (user directive
 * 2026-11-27, like the legacy "Gestione commessa"): each one shown only when
 * the server grants it, in the order the header used to list them.
 */
export function useWorkOrderActionsMenu({ workOrder, onChanged, onDeleted }: UseWorkOrderActionsMenuOptions) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const [dialog, setDialog] = useState<WorkOrderActionDialog | null>(null)
  const copy = useWorkOrderCopyQuoteAttributes(workOrder.id, onChanged)
  const reopen = useWorkOrderClosure(() => {
    toast.success(t('workOrders.actions.reopen.success'))
    onChanged?.()
  })
  const { remove, isPending: isDeleting } = useDeleteWorkOrder(onDeleted ?? NOOP)
  const { actions, resource } = workOrder.permissions
  const hasOffer = workOrder.quote !== null || workOrder.source_quotes.length > 0

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

  const handleDelete = async () => {
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

  const specs: MenuActionSpec[] = [
    {
      key: 'progress',
      label: t('workOrders.progress.action'),
      icon: TrendingUp,
      section: 'work',
      variant: 'default',
      visible: true,
      disabled: false,
      onSelect: () => setDialog('progress'),
    },
    {
      // The grid's "Responsabili e Partecipanti" popup (spec 0405), in place of the Info "Team" block (user directive 2026-11-27).
      key: 'team',
      label: t('workOrders.actions.team.label'),
      icon: Contact,
      section: 'work',
      variant: 'default',
      visible: true,
      disabled: false,
      onSelect: () => setDialog('team'),
    },
    {
      key: 'copyQuoteAttributes',
      label: t('workOrders.actions.copyQuoteAttributes.label'),
      icon: ClipboardCopy,
      section: 'work',
      variant: 'default',
      visible: Boolean(resource.update) && hasOffer,
      disabled: copy.isPending,
      onSelect: () => copy.mutate(),
    },
    {
      key: 'requestUpdate',
      label: t('workOrders.actions.requestUpdate.label'),
      icon: MessageSquareMore,
      section: 'work',
      variant: 'default',
      visible: Boolean(actions.request_update),
      disabled: false,
      onSelect: () => setDialog('requestUpdate'),
    },
    {
      key: 'proforma',
      label: t('proformaRequests.detailAction'),
      icon: Euro,
      section: 'work',
      variant: 'default',
      visible: Boolean(actions.request_proforma),
      disabled: false,
      onSelect: () => setDialog('proforma'),
    },
    {
      key: 'forceClose',
      label: t('actions.forceClose'),
      icon: Lock,
      section: 'closure',
      variant: 'destructive',
      visible: Boolean(actions.force_close),
      disabled: false,
      onSelect: () => setDialog('forceClose'),
    },
    {
      key: 'reopen',
      label: t('actions.reopen'),
      icon: LockOpen,
      section: 'closure',
      variant: 'default',
      visible: Boolean(actions.reopen),
      disabled: reopen.isPending,
      onSelect: () => void handleReopen(),
    },
    {
      key: 'delete',
      label: t('workOrders.actions.delete.label'),
      icon: Trash2,
      section: 'closure',
      variant: 'destructive',
      visible: onDeleted !== undefined && Boolean(actions.delete),
      disabled: isDeleting,
      onSelect: () => void handleDelete(),
    },
  ]

  return {
    items: specs.filter((spec) => spec.visible),
    dialog,
    closeDialog: () => setDialog(null),
  }
}
