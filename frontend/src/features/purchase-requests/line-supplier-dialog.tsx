import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Info, Truck } from 'lucide-react'
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
import { assignLinesSupplier, purchaseRequestKeys } from '@/features/purchase-requests/api'
import type { LineTarget } from '@/features/purchase-requests/line-status-transitions'
import { serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'
import { SupplierPicker } from '@/features/purchase-requests/purchase-request-supplier'
import type { LineSupplierResult, NamedRef } from '@/features/purchase-requests/types'

const SUPPLIER_LABEL_ID = 'purchase-line-supplier-target'

interface LineSupplierDialogProps {
  /** Selected lines; empty closes the dialog. Callers key it by the target ids so a reopen starts clean. */
  targets: readonly LineTarget[]
  onOpenChange: (open: boolean) => void
  onAssigned: (result: LineSupplierResult) => void
}

/**
 * Bulk "Assign supplier" (spec 0209 D-4): one supplier for every selected
 * line, all-or-nothing on the server. If any line can no longer change its
 * supplier (`abilities.update_supplier` false) it says so and cannot confirm.
 */
export function LineSupplierDialog({ targets, onOpenChange, onAssigned }: LineSupplierDialogProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [supplier, setSupplier] = useState<NamedRef | null>(null)
  const blocked = targets.filter((target) => !target.canUpdateSupplier)
  const count = targets.length

  const mutation = useMutation({
    mutationFn: (supplierId: number) =>
      assignLinesSupplier({ line_ids: targets.map((target) => target.id), supplier_id: supplierId }),
    onSuccess: (result) => {
      void queryClient.invalidateQueries({ queryKey: purchaseRequestKeys.all })
      toast.success(t('purchaseRequests.supplierDialog.done', { count: result.updated_count }))
      onAssigned(result)
    },
    onError: (error) => toast.error(serverMessage(error) ?? t('purchaseRequests.supplierDialog.error')),
  })

  const canConfirm = supplier !== null && blocked.length === 0 && count > 0 && !mutation.isPending

  return (
    <Dialog open={count > 0} onOpenChange={onOpenChange}>
      <DialogContent size="md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Truck className="size-4" aria-hidden="true" />
            {t('purchaseRequests.supplierDialog.title')}
          </DialogTitle>
          <DialogDescription>{t('purchaseRequests.supplierDialog.description', { count })}</DialogDescription>
        </DialogHeader>
        <div className="flex max-h-[70vh] flex-col gap-3 overflow-y-auto">
          <ul
            aria-label={t('purchaseRequests.statusDialog.selectedLines')}
            className="max-h-40 overflow-auto rounded-lg border border-border bg-card text-xs"
          >
            {targets.map((target) => (
              <li key={target.id} className="flex items-center justify-between gap-3 border-b border-border px-2 py-1 last:border-b-0">
                <span className="truncate" title={target.description}>
                  {target.id} - {target.description}
                </span>
                <span className="shrink-0 text-muted-foreground">{target.supplier?.name ?? '—'}</span>
              </li>
            ))}
          </ul>
          {blocked.length > 0 ? (
            <p role="alert" className="flex items-start gap-1.5 rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive">
              <Info className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
              {t('purchaseRequests.supplierDialog.blocked', { count: blocked.length })}
            </p>
          ) : null}
          <div className="flex flex-col gap-1.5">
            <Label className="text-xs" id={SUPPLIER_LABEL_ID}>
              {t('purchaseRequests.lines.supplier')}
              <span aria-hidden="true" className="text-destructive">*</span>
            </Label>
            <SupplierPicker
              selected={supplier}
              onSelect={setSupplier}
              label={t('purchaseRequests.lines.supplier')}
              disabled={blocked.length > 0}
              quickCreate
            />
          </div>
        </div>
        <DialogFooter>
          <Button type="button" variant="outline" className="bg-card" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button type="button" disabled={!canConfirm} onClick={() => supplier && mutation.mutate(supplier.id)}>
            {t('purchaseRequests.supplierDialog.confirm', { count })}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
