import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Tags } from 'lucide-react'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/forbidden-message'
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
import { RevenueTypeSelect } from '@/features/revenue-types/revenue-type-select'
import { setProductsRevenueType } from '@/features/products/api'
import type { ProductCostTypeTarget } from '@/features/products/product-cost-type-dialog'
import type { BulkProductCostTypeResult } from '@/features/products/types'

interface ProductRevenueTypeDialogProps {
  /** Selected products; empty closes the dialog. Callers key it by the target ids so a reopen starts clean. */
  targets: readonly ProductCostTypeTarget[]
  onOpenChange: (open: boolean) => void
  onApplied: (result: BulkProductCostTypeResult) => void
}

/**
 * Bulk "Set revenue sub-account" (spec 0350): one sub-account for every selected product,
 * all-or-nothing on the server. "None" removes it; the confirm stays disabled until a choice is made.
 */
export function ProductRevenueTypeDialog({ targets, onOpenChange, onApplied }: ProductRevenueTypeDialogProps) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const [revenueTypeId, setRevenueTypeId] = useState<number | null>(null)
  const [removing, setRemoving] = useState(false)
  const count = targets.length
  const hasChoice = removing || revenueTypeId !== null

  const mutation = useMutation({
    mutationFn: () =>
      setProductsRevenueType({ ids: targets.map((target) => target.id), revenue_type_id: removing ? null : revenueTypeId }),
    onSuccess: (result) => {
      void queryClient.invalidateQueries({ queryKey: ['products'] })
      toast.success(t('revenueTypes.bulk.done', { count: result.updated }))
      onApplied(result)
    },
    onError: (error) => toast.error(apiErrorMessage(error, t('revenueTypes.bulk.error'), i18n)),
  })

  return (
    <Dialog open={count > 0} onOpenChange={onOpenChange}>
      <DialogContent size="md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Tags className="size-4" aria-hidden="true" />
            {t('revenueTypes.bulk.title')}
          </DialogTitle>
          <DialogDescription>{t('revenueTypes.bulk.description', { count })}</DialogDescription>
        </DialogHeader>
        <div className="flex max-h-[70vh] flex-col gap-3 overflow-y-auto">
          <ul
            aria-label={t('revenueTypes.bulk.selected')}
            className="max-h-40 overflow-auto rounded-lg border border-border bg-card text-xs"
          >
            {targets.map((target) => (
              <li key={target.id} className="truncate border-b border-border px-2 py-1 last:border-b-0" title={target.name}>
                {target.name}
              </li>
            ))}
          </ul>
          <div className="flex flex-col gap-1.5">
            <Label className="text-xs">{t('revenueTypes.field.label')}</Label>
            <RevenueTypeSelect
              value={removing ? null : revenueTypeId}
              onChange={(next) => {
                setRemoving(false)
                setRevenueTypeId(next)
              }}
              aria-label={t('revenueTypes.field.label')}
            />
            <Label className="flex items-center gap-2 text-xs font-normal">
              <Checkbox
                checked={removing}
                onCheckedChange={(checked) => {
                  setRemoving(checked === true)
                  setRevenueTypeId(null)
                }}
              />
              {t('revenueTypes.bulk.none')}
            </Label>
          </div>
        </div>
        <DialogFooter>
          <Button type="button" variant="outline" className="bg-card" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button type="button" disabled={!hasChoice || count === 0 || mutation.isPending} onClick={() => mutation.mutate()}>
            {t('revenueTypes.bulk.confirm', { count })}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
