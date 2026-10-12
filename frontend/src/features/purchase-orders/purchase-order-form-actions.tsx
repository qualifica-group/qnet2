import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { FileText, GitMerge, History, ListChecks, Send, type LucideIcon } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { OutboundEmailComposerDialog } from '@/features/outbound-emails/outbound-email-composer-dialog'
import { PurchaseOrderMergeDialog } from '@/features/purchase-orders/purchase-order-merge-dialog'
import { PurchaseOrderMergeLogsDialog } from '@/features/purchase-orders/purchase-order-merge-logs-dialog'
import { PurchaseOrderStatusDialog } from '@/features/purchase-orders/purchase-order-status-dialog'
import { EDITABLE_STATUSES, type PurchaseOrder } from '@/features/purchase-orders/types'
import { usePurchaseOrderEmail } from '@/features/purchase-orders/use-purchase-order-email'
import { usePurchaseOrderPdf } from '@/features/purchase-orders/use-purchase-order-pdf'

type OpenDialog = 'status' | 'merge' | 'history' | null

interface PurchaseOrderFormActionsProps {
  /** The saved ODA the actions operate on. */
  order: PurchaseOrder
  /** Unsaved changes in the form: the merge is immediate on the server, so it refuses to open until they are saved. */
  isDirty?: boolean
  children?: ReactNode
}

interface ActionButtonProps {
  label: string
  tooltip: string
  icon: LucideIcon
  disabled?: boolean
  onClick: () => void
}

/** Icon plus xs text on sm+, icon only on mobile (the label stays the accessible name); filled outline so it stands off the page. */
function ActionButton({ label, tooltip, icon: Icon, disabled, onClick }: ActionButtonProps) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button type="button" variant="outline" size="xs" aria-label={label} disabled={disabled} onClick={onClick}>
          <Icon aria-hidden="true" />
          <span className="hidden sm:inline">{label}</span>
        </Button>
      </TooltipTrigger>
      <TooltipContent>{tooltip}</TooltipContent>
    </Tooltip>
  )
}

/**
 * Record-level actions of the ODA form header (rendered only for a saved
 * ODA, next to the status badge): change status, merge, merge history, PDF
 * and send to the supplier. Each gates itself on `order.abilities`; the
 * server re-authorizes every call.
 */
export function PurchaseOrderFormActions({ order, isDirty = false, children }: PurchaseOrderFormActionsProps) {
  const { t } = useTranslation()
  const [dialog, setDialog] = useState<OpenDialog>(null)
  const pdf = usePurchaseOrderPdf()
  const email = usePurchaseOrderEmail()
  const closeDialog = (open: boolean) => (open ? undefined : setDialog(null))
  const canMerge = order.abilities.merge && EDITABLE_STATUSES.includes(order.status)

  const openMerge = () => {
    if (isDirty) {
      toast.warning(t('purchaseOrderActions.merge.unsavedChanges'))
      return
    }
    setDialog('merge')
  }

  return (
    <TooltipProvider>
      <div
        role="group"
        aria-label={t('purchaseOrderForm.actionsLabel')}
        className="flex flex-wrap items-center gap-1.5 empty:hidden"
      >
        <ActionButton
          label={t('purchaseOrderActions.buttons.status')}
          tooltip={t('purchaseOrderActions.tooltips.status')}
          icon={ListChecks}
          onClick={() => setDialog('status')}
        />
        {canMerge ? (
          <ActionButton
            label={t('purchaseOrderActions.buttons.merge')}
            tooltip={t('purchaseOrderActions.tooltips.merge')}
            icon={GitMerge}
            onClick={openMerge}
          />
        ) : null}
        {order.merge_logs_count > 0 ? (
          <ActionButton
            label={t('purchaseOrderActions.buttons.history')}
            tooltip={t('purchaseOrderActions.tooltips.history')}
            icon={History}
            onClick={() => setDialog('history')}
          />
        ) : null}
        <ActionButton
          label={t('purchaseOrderActions.buttons.pdf')}
          tooltip={t('purchaseOrderActions.tooltips.pdf')}
          icon={FileText}
          disabled={pdf.isDownloading}
          onClick={() => void pdf.download(order.id)}
        />
        {order.abilities.send ? (
          <ActionButton
            label={t('purchaseOrderActions.buttons.send')}
            tooltip={t('purchaseOrderActions.tooltips.send')}
            icon={Send}
            disabled={email.isStarting}
            onClick={() => email.startEmail(order.id)}
          />
        ) : null}
        {children}
      </div>

      {dialog === 'status' ? <PurchaseOrderStatusDialog order={order} onOpenChange={closeDialog} /> : null}
      {dialog === 'merge' ? <PurchaseOrderMergeDialog order={order} onOpenChange={closeDialog} /> : null}
      {dialog === 'history' ? <PurchaseOrderMergeLogsDialog order={order} onOpenChange={closeDialog} /> : null}
      {email.target ? (
        <OutboundEmailComposerDialog
          key={email.target.emailId}
          owner={email.target.owner}
          emailId={email.target.emailId}
          justCreated
          prefillDefaultTo
          open
          onOpenChange={(open) => (open ? undefined : email.closeComposer())}
        />
      ) : null}
    </TooltipProvider>
  )
}
