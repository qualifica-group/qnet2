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
import { bulkChangePurchaseOrderStatus, purchaseOrderKeys } from '@/features/purchase-orders/api'
import { BULK_TARGET_STATUSES, type BulkStatusResult } from '@/features/purchase-orders/purchase-order-bulk-status'
import { PurchaseOrderStatusOptions } from '@/features/purchase-orders/purchase-order-status-options'
import type { PurchaseOrderStatus } from '@/features/purchase-orders/types'
import { usePurchaseOrderStatusCatalog } from '@/features/purchase-order-statuses/use-purchase-order-status-catalog'
import { purchaseRequestKeys } from '@/features/purchase-requests/api'
import { serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'

const STATUS_LABEL_ID = 'purchase-order-bulk-status-target'

interface PurchaseOrderBulkStatusDialogProps {
  ids: number[]
  onOpenChange: (open: boolean) => void
  /** Called after a successful application (refresh the grid, clear the selection). */
  onApplied: () => void
}

/**
 * Bulk status change of the selected ODA (spec 0433): choosing a status runs a
 * dry run that says how many orders can move and why the others cannot; the
 * confirmation applies it. Mounted only while open, so each opening starts clean.
 */
export function PurchaseOrderBulkStatusDialog({ ids, onOpenChange, onApplied }: PurchaseOrderBulkStatusDialogProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { nameOf } = usePurchaseOrderStatusCatalog()
  const [choice, setChoice] = useState<PurchaseOrderStatus | null>(null)

  const preview = useMutation({
    mutationFn: (toStatus: PurchaseOrderStatus) =>
      bulkChangePurchaseOrderStatus({ ids, to_status: toStatus, dry_run: true }),
    onError: (error) => toast.error(serverMessage(error) ?? t('purchaseOrderActions.bulkStatus.previewError')),
  })

  const apply = useMutation({
    mutationFn: (toStatus: PurchaseOrderStatus) =>
      bulkChangePurchaseOrderStatus({ ids, to_status: toStatus, dry_run: false }),
    onSuccess: (result) => {
      void queryClient.invalidateQueries({ queryKey: purchaseOrderKeys.all })
      // Cancelling frees the linked RDA lines (spec 0244), as in the single change
      void queryClient.invalidateQueries({ queryKey: purchaseRequestKeys.all })
      toast.success(t('purchaseOrderActions.bulkStatus.done', { changed: result.changed.length, skipped: result.skipped.length }))
      onApplied()
      onOpenChange(false)
    },
    onError: (error) => toast.error(serverMessage(error) ?? t('purchaseOrderActions.bulkStatus.error')),
  })

  const choose = (status: PurchaseOrderStatus) => {
    setChoice(status)
    preview.mutate(status)
  }

  const result: BulkStatusResult | undefined = preview.data
  const canConfirm = choice !== null && result !== undefined && result.changed.length > 0 && !preview.isPending && !apply.isPending

  return (
    <Dialog open onOpenChange={onOpenChange}>
      <DialogContent size="sm">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <ListChecks className="size-4" aria-hidden="true" />
            {t('purchaseOrderActions.bulkStatus.title')}
          </DialogTitle>
          <DialogDescription>{t('purchaseOrderActions.bulkStatus.subtitle', { count: ids.length })}</DialogDescription>
        </DialogHeader>
        <div className="flex flex-col gap-3">
          <div className="flex flex-col gap-1.5">
            <Label className="text-xs" id={STATUS_LABEL_ID}>
              {t('purchaseOrderActions.status.newStatus')}
            </Label>
            <PurchaseOrderStatusOptions
              labelledBy={STATUS_LABEL_ID}
              options={BULK_TARGET_STATUSES}
              value={choice}
              onValueChange={choose}
            />
          </div>
          <div role="status" aria-live="polite" className="flex flex-col gap-2 text-xs">
            {preview.isPending ? <p>{t('purchaseOrderActions.bulkStatus.checking')}</p> : null}
            {result && choice !== null && !preview.isPending ? (
              <>
                <p className="font-medium">
                  {t('purchaseOrderActions.bulkStatus.preview', {
                    changed: result.changed.length,
                    total: result.total,
                    status: nameOf(choice),
                  })}
                </p>
                {result.skipped.length > 0 ? (
                  <div className="flex flex-col gap-1">
                    <p className="text-muted-foreground">{t('purchaseOrderActions.bulkStatus.skippedTitle')}</p>
                    <ul className="max-h-40 overflow-auto rounded-md border bg-surface px-3 py-2">
                      {result.skipped.map((item) => (
                        <li key={item.id}>
                          <span className="font-medium">{item.code}</span>
                          {': '}
                          {item.reason}
                        </li>
                      ))}
                    </ul>
                  </div>
                ) : null}
              </>
            ) : null}
          </div>
        </div>
        <DialogFooter>
          <Button type="button" variant="outline" className="bg-card" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button type="button" disabled={!canConfirm} onClick={() => choice !== null && apply.mutate(choice)}>
            {t('purchaseOrderActions.bulkStatus.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
