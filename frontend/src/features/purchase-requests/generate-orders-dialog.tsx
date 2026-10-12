import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { FileText, Info } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { COST_TYPES_FOR_SELECT_RESOURCE } from '@/features/cost-types/for-select-api'
import { PAYMENT_METHODS_FOR_SELECT_RESOURCE } from '@/features/payment-methods/for-select-api'
import { purchaseOrderKeys } from '@/features/purchase-orders/api'
import { generatePurchaseOrders, purchaseRequestKeys } from '@/features/purchase-requests/api'
import { generateOrdersErrors, groupBySupplier } from '@/features/purchase-requests/generate-orders'
import { GenerateOrdersResultView } from '@/features/purchase-requests/generate-orders-result'
import type { LineTarget } from '@/features/purchase-requests/line-status-transitions'
import { todayIso } from '@/features/purchase-requests/purchase-request-payload'
import type { GenerateOrdersResult } from '@/features/purchase-requests/types'

interface GenerateOrdersDialogProps {
  /** Selected lines; empty closes the dialog. Callers key it by the target ids so a reopen starts clean. */
  targets: readonly LineTarget[]
  onOpenChange: (open: boolean) => void
  /** Fired once the orders exist (the dialog stays open on the outcome): refresh the grid. */
  onGenerated: (result: GenerateOrdersResult) => void
}

/**
 * Bulk "Generate ODA" (spec 0210 D-17): recaps the selected lines per supplier,
 * asks for the cost type (required), payment method (optional) and order date,
 * then shows links to the created orders. All-or-nothing on the server.
 */
export function GenerateOrdersDialog({ targets, onOpenChange, onGenerated }: GenerateOrdersDialogProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [costTypeId, setCostTypeId] = useState<number | null>(null)
  const [paymentMethodId, setPaymentMethodId] = useState<number | null>(null)
  const [orderDate, setOrderDate] = useState(() => todayIso())
  const groups = groupBySupplier(targets)

  const mutation = useMutation({
    mutationFn: (costType: number) =>
      generatePurchaseOrders({
        line_ids: targets.map((target) => target.id),
        cost_type_id: costType,
        ...(paymentMethodId === null ? {} : { payment_method_id: paymentMethodId }),
        ...(orderDate === '' ? {} : { order_date: orderDate }),
      }),
    onSuccess: (result) => {
      void queryClient.invalidateQueries({ queryKey: purchaseRequestKeys.all })
      void queryClient.invalidateQueries({ queryKey: purchaseOrderKeys.all })
      onGenerated(result)
    },
  })

  const labels = (placeholder: string, trigger: string) => ({
    placeholder,
    searchPlaceholder: t('purchaseRequests.select.search'),
    empty: t('purchaseRequests.select.empty'),
    error: t('purchaseRequests.select.error'),
    clearLabel: t('common.clear'),
    triggerLabel: trigger,
    retry: t('common.retry'),
  })
  const errors = mutation.isError ? generateOrdersErrors(mutation.error) : []
  const canConfirm = costTypeId !== null && targets.length > 0 && !mutation.isPending

  return (
    <Dialog open={targets.length > 0} onOpenChange={onOpenChange}>
      <DialogContent size="md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <FileText className="size-4" aria-hidden="true" />
            {t('purchaseRequests.generateOrders.title')}
          </DialogTitle>
          <DialogDescription>
            {t('purchaseRequests.generateOrders.description', { count: targets.length })}
          </DialogDescription>
        </DialogHeader>
        {mutation.data ? (
          <GenerateOrdersResultView result={mutation.data} />
        ) : (
          <div className="flex max-h-[70vh] flex-col gap-3 overflow-y-auto">
            <div className="flex flex-col gap-1.5">
              <p className="text-xs font-medium">{t('purchaseRequests.generateOrders.preview', { count: groups.length })}</p>
              <ul aria-label={t('purchaseRequests.generateOrders.groups')} className="max-h-40 overflow-auto rounded-lg border border-border bg-card text-xs">
                {groups.map((group) => (
                  <li key={group.supplier.id} className="flex items-center justify-between gap-3 border-b border-border px-2 py-1 last:border-b-0">
                    <span className="truncate font-medium">{group.supplier.name}</span>
                    <span className="shrink-0 text-muted-foreground">
                      {t('purchaseRequests.generateOrders.linesCount', { count: group.lines.length })}
                    </span>
                  </li>
                ))}
              </ul>
              <p className="text-xs text-muted-foreground">{t('purchaseRequests.generateOrders.previewNote')}</p>
            </div>
            <div className="flex flex-col gap-1.5">
              <Label className="text-xs">
                {t('purchaseRequests.generateOrders.costType')}
                <span aria-hidden="true" className="text-destructive">*</span>
              </Label>
              <AsyncPaginatedSelect
                resource={COST_TYPES_FOR_SELECT_RESOURCE}
                value={costTypeId}
                onChange={setCostTypeId}
                labels={labels(t('purchaseRequests.generateOrders.costTypePlaceholder'), t('purchaseRequests.generateOrders.costType'))}
              />
            </div>
            <div className="flex flex-col gap-1.5">
              <Label className="text-xs">{t('purchaseRequests.generateOrders.paymentMethod')}</Label>
              <AsyncPaginatedSelect
                resource={PAYMENT_METHODS_FOR_SELECT_RESOURCE}
                value={paymentMethodId}
                onChange={setPaymentMethodId}
                labels={labels(t('purchaseRequests.generateOrders.paymentMethodPlaceholder'), t('purchaseRequests.generateOrders.paymentMethod'))}
              />
            </div>
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="generate-orders-date" className="text-xs">
                {t('purchaseRequests.generateOrders.orderDate')}
              </Label>
              <Input id="generate-orders-date" type="date" value={orderDate} onChange={(event) => setOrderDate(event.target.value)} />
            </div>
            {mutation.isError ? (
              <div role="alert" className="flex items-start gap-1.5 rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive">
                <Info className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                <div className="flex flex-col gap-0.5">
                  <span>{t('purchaseRequests.generateOrders.error')}</span>
                  {errors.map((message) => (
                    <span key={message}>{message}</span>
                  ))}
                </div>
              </div>
            ) : null}
          </div>
        )}
        <DialogFooter>
          <Button type="button" variant="outline" className="bg-card" onClick={() => onOpenChange(false)}>
            {mutation.data ? t('common.close') : t('common.cancel')}
          </Button>
          {mutation.data ? null : (
            <Button type="button" disabled={!canConfirm} onClick={() => costTypeId !== null && mutation.mutate(costTypeId)}>
              {t('purchaseRequests.generateOrders.confirm', { count: groups.length })}
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
