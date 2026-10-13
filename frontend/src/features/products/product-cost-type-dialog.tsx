import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Tags } from 'lucide-react'
import { toast } from 'sonner'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { costTypeOptionRenderer } from '@/features/cost-types/cost-type-option-renderer'
import { COST_TYPES_FOR_SELECT_RESOURCE } from '@/features/cost-types/for-select-api'
import { setProductsCostType } from '@/features/products/api'
import type { BulkProductCostTypeResult } from '@/features/products/types'

/** A selected product as the dialog lists it. */
export interface ProductCostTypeTarget {
  id: number
  name: string
}

interface ProductCostTypeDialogProps {
  /** Selected products; empty closes the dialog. Callers key it by the target ids so a reopen starts clean. */
  targets: readonly ProductCostTypeTarget[]
  onOpenChange: (open: boolean) => void
  onApplied: (result: BulkProductCostTypeResult) => void
}

/**
 * Bulk "Set cost type" (spec 0251 D-1/D-6): one cost type for every selected
 * product, all-or-nothing on the server. "None" removes the type; the choice
 * is explicit (the confirm stays disabled until it is made).
 */
export function ProductCostTypeDialog({ targets, onOpenChange, onApplied }: ProductCostTypeDialogProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [costTypeId, setCostTypeId] = useState<number | null>(null)
  const [removing, setRemoving] = useState(false)
  const count = targets.length
  const hasChoice = removing || costTypeId !== null

  const mutation = useMutation({
    mutationFn: () =>
      setProductsCostType({ product_ids: targets.map((target) => target.id), cost_type_id: removing ? null : costTypeId }),
    onSuccess: (result) => {
      void queryClient.invalidateQueries({ queryKey: ['products'] })
      toast.success(t('products.bulkCostType.done', { count: result.updated }))
      onApplied(result)
    },
    onError: () => toast.error(t('products.bulkCostType.error')),
  })

  return (
    <Dialog open={count > 0} onOpenChange={onOpenChange}>
      <DialogContent size="md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Tags className="size-4" aria-hidden="true" />
            {t('products.bulkCostType.title')}
          </DialogTitle>
          <DialogDescription>{t('products.bulkCostType.description', { count })}</DialogDescription>
        </DialogHeader>
        <div className="flex max-h-[70vh] flex-col gap-3 overflow-y-auto">
          <ul
            aria-label={t('products.bulkCostType.selected')}
            className="max-h-40 overflow-auto rounded-lg border border-border bg-card text-xs"
          >
            {targets.map((target) => (
              <li key={target.id} className="truncate border-b border-border px-2 py-1 last:border-b-0" title={target.name}>
                {target.name}
              </li>
            ))}
          </ul>
          <div className="flex flex-col gap-1.5">
            <Label className="text-xs">{t('products.bulkCostType.field')}</Label>
            <AsyncPaginatedSelect
              resource={COST_TYPES_FOR_SELECT_RESOURCE}
              value={removing ? null : costTypeId}
              onChange={(next) => {
                setRemoving(false)
                setCostTypeId(next)
              }}
              renderItem={costTypeOptionRenderer}
              labels={{
                placeholder: t('products.bulkCostType.placeholder'),
                searchPlaceholder: t('products.form.costTypeSearch'),
                empty: t('products.form.costTypeEmpty'),
                error: t('products.form.costTypeError'),
                clearLabel: t('common.clear'),
                triggerLabel: t('products.bulkCostType.field'),
                retry: t('common.retry'),
              }}
            />
            <Label className="flex items-center gap-2 text-xs font-normal">
              <Checkbox
                checked={removing}
                onCheckedChange={(checked) => {
                  setRemoving(checked === true)
                  setCostTypeId(null)
                }}
              />
              {t('products.bulkCostType.none')}
            </Label>
          </div>
        </div>
        <DialogFooter>
          <Button type="button" variant="outline" className="bg-card" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button type="button" disabled={!hasChoice || count === 0 || mutation.isPending} onClick={() => mutation.mutate()}>
            {t('products.bulkCostType.confirm', { count })}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
