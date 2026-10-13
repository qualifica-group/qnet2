import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ListChecks } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { changePurchaseOrderStatus, purchaseOrderKeys } from '@/features/purchase-orders/api'
import { purchaseRequestKeys } from '@/features/purchase-requests/api'
import { PurchaseOrderStatusBadge } from '@/features/purchase-orders/purchase-order-status-badge'
import { PurchaseOrderStatusOptions } from '@/features/purchase-orders/purchase-order-status-options'
import type { PurchaseOrder, PurchaseOrderStatus } from '@/features/purchase-orders/types'
import { usePurchaseOrderStatusCatalog } from '@/features/purchase-order-statuses/use-purchase-order-status-catalog'
import { serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'

const STATUS_LABEL_ID = 'purchase-order-status-target'

interface PurchaseOrderStatusDialogProps {
  order: PurchaseOrder
  onOpenChange: (open: boolean) => void
}

/**
 * Status change of an ODA (D-9). Offers only `abilities.transitions` (the
 * server is the source of truth); with none it says so and cannot confirm.
 * Mounted only while open, so each opening starts without a choice.
 */
export function PurchaseOrderStatusDialog({ order, onOpenChange }: PurchaseOrderStatusDialogProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { nameOf } = usePurchaseOrderStatusCatalog()
  const [choice, setChoice] = useState<PurchaseOrderStatus | null>(null)
  const options = order.abilities.transitions

  const mutation = useMutation({
    mutationFn: (toStatus: PurchaseOrderStatus) => changePurchaseOrderStatus(order.id, toStatus),
    onSuccess: (updated) => {
      void queryClient.invalidateQueries({ queryKey: purchaseOrderKeys.all })
      // The order status and deletion move the linked RDA lines (spec 0244 D-2/D-3)
      void queryClient.invalidateQueries({ queryKey: purchaseRequestKeys.all })
      toast.success(t('purchaseOrderActions.status.done', { status: nameOf(updated.status) }))
      onOpenChange(false)
    },
    onError: (error) => toast.error(serverMessage(error) ?? t('purchaseOrderActions.status.error')),
  })

  const canConfirm = choice !== null && options.includes(choice) && !mutation.isPending

  return (
    <Dialog open onOpenChange={onOpenChange}>
      <DialogContent size="sm">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <ListChecks className="size-4" aria-hidden="true" />
            {t('purchaseOrderActions.status.title')}
          </DialogTitle>
          <DialogDescription>{t('purchaseOrderActions.status.subtitle', { code: order.code })}</DialogDescription>
        </DialogHeader>
        <div className="flex flex-col gap-3">
          <div className="flex items-center gap-2 text-xs">
            <span className="font-medium">{t('purchaseOrderActions.status.current')}</span>
            <PurchaseOrderStatusBadge status={order.status} />
          </div>
          {options.length === 0 ? (
            <p role="status" className="rounded-md border bg-surface px-3 py-2 text-xs">
              {t('purchaseOrderActions.status.none')}
            </p>
          ) : (
            <div className="flex flex-col gap-1.5">
              <Label className="text-xs" id={STATUS_LABEL_ID}>
                {t('purchaseOrderActions.status.newStatus')}
              </Label>
              <PurchaseOrderStatusOptions
                labelledBy={STATUS_LABEL_ID}
                options={options}
                value={choice}
                onValueChange={setChoice}
              />
            </div>
          )}
        </div>
        <DialogFooter>
          <Button type="button" variant="outline" className="bg-card" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button type="button" disabled={!canConfirm} onClick={() => choice !== null && mutation.mutate(choice)}>
            {t('purchaseOrderActions.status.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
