import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { PRODUCTS_FOR_SELECT_RESOURCE } from '@/features/products/for-select-api'
import { ImportConfigMultiSelect } from '@/features/imports/wizard/import-config-multi-select'
import { resolveImportWizardErrorMessage } from '@/features/imports/wizard/resolve-error-message'

export interface ReviewBulkProductsDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** How many rows are selected; drives the description copy. */
  selectionCount: number
  /** Scopes the picker exactly like `ProductsOfInterestField`/the per-row popup. */
  campaignCategoryIds: number[]
  /** PATCHes the combined bulk products assignment; rejects (surfaced inline) on failure. */
  onApply: (productIds: number[]) => Promise<void>
}

/**
 * Bulk "Assegna prodotti" popup, opened from the review bar's actions
 * dropdown (spec 0094 bulk delta). Assign-only (decisione utente): unlike the
 * per-row popup (`review-products-editor.tsx`), it has no "use default"/"no
 * products" shortcuts — those stay row-scoped — so Applica stays disabled
 * while the picker is empty.
 */
export function ReviewBulkProductsDialog({
  open,
  onOpenChange,
  selectionCount,
  campaignCategoryIds,
  onApply,
}: ReviewBulkProductsDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent size="sm">
        <ReviewBulkProductsDialogBody
          selectionCount={selectionCount}
          campaignCategoryIds={campaignCategoryIds}
          onApply={onApply}
          onClose={() => onOpenChange(false)}
        />
      </DialogContent>
    </Dialog>
  )
}

interface ReviewBulkProductsDialogBodyProps {
  selectionCount: number
  campaignCategoryIds: number[]
  onApply: ReviewBulkProductsDialogProps['onApply']
  onClose: () => void
}

/**
 * Radix unmounts `DialogContent`'s subtree while closed (mirrors
 * `AssignOperatorsDialogBody`), so keeping the picker state in its own
 * component makes every open start from an empty selection.
 */
function ReviewBulkProductsDialogBody({
  selectionCount,
  campaignCategoryIds,
  onApply,
  onClose,
}: ReviewBulkProductsDialogBodyProps) {
  const { t } = useTranslation('importWizard')
  const [productIds, setProductIds] = useState<number[]>([])
  const [isApplying, setIsApplying] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const params = useMemo(() => ({ category_ids: campaignCategoryIds }), [campaignCategoryIds])

  // Step 1: PATCH the picker's current product ids as the bulk `product_ids`
  // assignment. Step 2: on success, close the popup (the grid selection/rows
  // are refreshed by the caller); on failure, keep it open and surface the
  // error inline so the operator can adjust and retry.
  function handleApply() {
    setIsApplying(true)
    setError(null)
    onApply(productIds)
      .then(() => onClose())
      .catch((cause: unknown) => setError(resolveImportWizardErrorMessage(cause, t)))
      .finally(() => setIsApplying(false))
  }

  return (
    <>
      <DialogHeader>
        <DialogTitle>{t('review.bulkAssign.products.title')}</DialogTitle>
        <DialogDescription>
          {t('review.bulkAssign.products.description', { count: selectionCount })}
        </DialogDescription>
      </DialogHeader>

      <ImportConfigMultiSelect
        resource={PRODUCTS_FOR_SELECT_RESOURCE}
        value={productIds}
        onChange={setProductIds}
        triggerLabel={t('review.bulkAssign.products.title')}
        params={params}
        disabled={isApplying}
      />

      {error ? (
        <p role="alert" className="text-xs text-destructive">
          {error}
        </p>
      ) : null}

      <DialogFooter>
        <Button type="button" variant="outline" size="sm" onClick={onClose} disabled={isApplying}>
          {t('review.bulkAssign.products.cancel')}
        </Button>
        <Button
          type="button"
          size="sm"
          onClick={handleApply}
          disabled={isApplying || productIds.length === 0}
        >
          {t('review.bulkAssign.products.apply')}
        </Button>
      </DialogFooter>
    </>
  )
}
