import { ClipboardCopy } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { ACTION_BUTTON_VARIANT } from '@/features/table/action-tone'
import { useWorkOrderCopyQuoteAttributes } from '@/features/work-orders/use-work-order-copy-quote-attributes'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

interface WorkOrderCopyQuoteAttributesActionProps {
  workOrder: WorkOrderDetailWithPermissions
  onChanged?: () => void
}

/** Spec 0404 D-D2: header action, shown to who may update a work order that has an offer. */
export function WorkOrderCopyQuoteAttributesAction({ workOrder, onChanged }: WorkOrderCopyQuoteAttributesActionProps) {
  const { t } = useTranslation()
  const copy = useWorkOrderCopyQuoteAttributes(workOrder.id, onChanged)
  const hasOffer = workOrder.quote !== null || workOrder.source_quotes.length > 0

  if (!workOrder.permissions.resource.update || !hasOffer) {
    return null
  }

  return (
    <Button
      type="button"
      variant={ACTION_BUTTON_VARIANT.action}
      className="bg-card"
      size="sm"
      disabled={copy.isPending}
      onClick={() => copy.mutate()}
    >
      <ClipboardCopy aria-hidden="true" />
      {t('workOrders.actions.copyQuoteAttributes.label')}
    </Button>
  )
}
