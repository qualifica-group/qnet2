import { useTranslation } from 'react-i18next'
import { AlertTriangle, GitMerge } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { PurchaseOrderMergeCandidate } from '@/features/purchase-orders/purchase-order-merge-candidate'
import type { PurchaseOrder } from '@/features/purchase-orders/types'
import { usePurchaseOrderMerge } from '@/features/purchase-orders/use-purchase-order-merge'

interface PurchaseOrderMergeDialogProps {
  order: PurchaseOrder
  onOpenChange: (open: boolean) => void
}

/**
 * Merge dialog (D-19): pick whole candidate orders or single lines and move
 * them here. Mounted only while open; the caller refuses to open it while the
 * form has unsaved changes, because the server merges immediately.
 */
export function PurchaseOrderMergeDialog({ order, onOpenChange }: PurchaseOrderMergeDialogProps) {
  const { t } = useTranslation()
  const merge = usePurchaseOrderMerge(order.id, () => onOpenChange(false))
  const { candidates } = merge
  const list = candidates.data ?? []

  return (
    <Dialog open onOpenChange={onOpenChange}>
      <DialogContent size="lg">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <GitMerge className="size-4" aria-hidden="true" />
            {t('purchaseOrderActions.merge.title')}
          </DialogTitle>
          <DialogDescription>{t('purchaseOrderActions.merge.subtitle', { code: order.code })}</DialogDescription>
        </DialogHeader>
        <div className="flex max-h-[60vh] flex-col gap-3 overflow-y-auto">
          {candidates.isLoading ? (
            <div role="status" aria-label={t('purchaseOrderActions.merge.loading')} className="flex flex-col gap-2">
              <Skeleton className="h-16 w-full" />
              <Skeleton className="h-16 w-full" />
            </div>
          ) : candidates.isError ? (
            <p role="alert" className="text-sm text-destructive">
              {t('purchaseOrderActions.merge.loadError')}
            </p>
          ) : list.length === 0 ? (
            <p role="status" className="rounded-md border bg-surface px-3 py-2 text-xs">
              {t('purchaseOrderActions.merge.empty')}
            </p>
          ) : (
            <ul className="flex flex-col gap-2">
              {list.map((candidate) => (
                <PurchaseOrderMergeCandidate
                  key={candidate.id}
                  candidate={candidate}
                  selected={merge.selected}
                  onToggleOrder={merge.toggleOrder}
                  onToggleLine={merge.toggleLine}
                />
              ))}
            </ul>
          )}
        </div>
        <p className="flex items-start gap-1.5 text-xs text-muted-foreground">
          <AlertTriangle className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
          {t('purchaseOrderActions.merge.emptiedWarning')}
        </p>
        <DialogFooter>
          <Button type="button" variant="outline" className="bg-card" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button type="button" disabled={!merge.canConfirm} onClick={merge.confirm}>
            {t('purchaseOrderActions.merge.confirm', { count: merge.selected.size })}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
