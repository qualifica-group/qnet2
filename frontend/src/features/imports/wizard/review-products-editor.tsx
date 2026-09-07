import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import type { ICellRendererParams, IRowNode } from 'ag-grid-community'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'
import { PRODUCTS_FOR_SELECT_RESOURCE } from '@/features/products/for-select-api'
import { ImportConfigMultiSelect } from '@/features/imports/wizard/import-config-multi-select'
import { resolveImportWizardErrorMessage } from '@/features/imports/wizard/resolve-error-message'
import type { ImportRunRowItem } from '@/features/imports/wizard/types'

/**
 * Shared, stable state/callback threaded through `gridOptions.context`
 * (review-grid.tsx), mirroring `ReviewOperatorGridContext`: the products
 * column opens a popup and applies through the same mutation regardless of
 * which row triggered it. `hasGlobalDefaultProducts` gates only the cell's
 * "uses default" hint (spec 0094 D-4: the run's `product_ids` global config);
 * `campaignCategoryIds` scopes the popup's picker exactly like
 * `ProductsOfInterestField` on the Lead form.
 */
export interface ReviewProductsGridContext {
  onApplyProducts: (
    row: ImportRunRowItem,
    productIds: number[] | null,
    node: IRowNode<ImportRunRowItem>,
  ) => Promise<void>
  hasGlobalDefaultProducts: boolean
  campaignCategoryIds: number[]
}

export interface ReviewProductsCellParams
  extends ICellRendererParams<ImportRunRowItem, unknown, ReviewProductsGridContext> {
  /** Forces plain text with no popup affordance, mirroring `ReviewOperatorCell` (spec 0034 AC-013). */
  readOnly?: boolean
}

/**
 * Resolves the cell's display text (spec 0094 AC-055): `product_ids === null`
 * inherits the run's global default (a muted hint only when one is actually
 * configured); `[]` is the distinct "no products on this row" state; a
 * non-empty override lists the row's own hydrated `products` labels.
 */
function productsDisplayValue(row: ImportRunRowItem, t: TFunction, hasGlobalDefault: boolean): string {
  if (row.product_ids === null) {
    return hasGlobalDefault ? t('review.products.usingDefault') : '—'
  }
  if (row.product_ids.length === 0) {
    return t('review.products.none')
  }
  return row.products.map((product) => product.label).join(', ')
}

/**
 * Per-row products-of-interest override cell (spec 0094 D-4/AC-055): shows
 * the row's own `products` when overridden, the "uses default" hint when
 * inheriting a configured global default, or `—`. Not `readOnly`, the cell is
 * a button opening a popup with a products picker (`ImportConfigMultiSelect`)
 * precompiled from the row's current override.
 */
export function ReviewProductsCell({ data, node, context, readOnly }: ReviewProductsCellParams) {
  const { t } = useTranslation('importWizard')
  const [open, setOpen] = useState(false)

  if (!data) return null

  const displayValue = productsDisplayValue(data, t, context.hasGlobalDefaultProducts)

  if (readOnly) {
    return <span className="truncate text-xs text-muted-foreground">{displayValue}</span>
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <button
          type="button"
          className="block w-full truncate text-left text-xs underline-offset-2 hover:underline"
          aria-label={t('review.products.editLabel')}
        >
          {displayValue}
        </button>
      </DialogTrigger>
      <DialogContent size="sm">
        <ReviewProductsDialogBody
          row={data}
          node={node}
          onApplyProducts={context.onApplyProducts}
          campaignCategoryIds={context.campaignCategoryIds}
          onClose={() => setOpen(false)}
        />
      </DialogContent>
    </Dialog>
  )
}

interface ReviewProductsDialogBodyProps {
  row: ImportRunRowItem
  node: IRowNode<ImportRunRowItem>
  onApplyProducts: ReviewProductsGridContext['onApplyProducts']
  campaignCategoryIds: number[]
  onClose: () => void
}

/**
 * The popup's own content: a controlled `ImportConfigMultiSelect` seeded from
 * the row's current override, two distinct reset shortcuts — "use default"
 * (`null`, revert to the run's global config) and "no products on this row"
 * (`[]`, an explicit empty override) — and the Applica/Annulla actions
 * (mirrors `ReviewOperatorDialogBody`). Annulla and the dialog's own close
 * affordances never call `onApplyProducts` — only the Applica click does.
 */
function ReviewProductsDialogBody({
  row,
  node,
  onApplyProducts,
  campaignCategoryIds,
  onClose,
}: ReviewProductsDialogBodyProps) {
  const { t } = useTranslation('importWizard')
  const [productIds, setProductIds] = useState<number[] | null>(row.product_ids)
  const [isApplying, setIsApplying] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const params = useMemo(() => ({ category_ids: campaignCategoryIds }), [campaignCategoryIds])

  // Step 1: PATCH the popup's current product ids (or `null` to revert to
  // the run default, or `[]` for an explicit empty override) as
  // `product_ids`. Step 2: on success, close the popup (the grid row/counts
  // are refreshed by the caller's `onApplyProducts`); on failure, keep it
  // open and surface the error inline so the operator can adjust and retry.
  function handleApply() {
    setIsApplying(true)
    setError(null)
    onApplyProducts(row, productIds, node)
      .then(() => onClose())
      .catch((cause: unknown) => setError(resolveImportWizardErrorMessage(cause, t)))
      .finally(() => setIsApplying(false))
  }

  return (
    <>
      <DialogHeader>
        <DialogTitle>{t('review.products.title')}</DialogTitle>
        <DialogDescription>{t('review.products.description')}</DialogDescription>
      </DialogHeader>

      <ImportConfigMultiSelect
        resource={PRODUCTS_FOR_SELECT_RESOURCE}
        value={productIds ?? []}
        onChange={setProductIds}
        triggerLabel={t('review.products.title')}
        params={params}
        disabled={isApplying}
        selectedItems={row.products}
      />

      {error ? (
        <p role="alert" className="text-xs text-destructive">
          {error}
        </p>
      ) : null}

      <DialogFooter>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="mr-auto"
          onClick={() => setProductIds(null)}
          disabled={isApplying || productIds === null}
        >
          {t('review.products.useDefault')}
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={() => setProductIds([])}
          disabled={isApplying || (Array.isArray(productIds) && productIds.length === 0)}
        >
          {t('review.products.clearSelection')}
        </Button>
        <Button type="button" variant="outline" size="sm" onClick={onClose} disabled={isApplying}>
          {t('review.products.cancel')}
        </Button>
        <Button type="button" size="sm" onClick={handleApply} disabled={isApplying}>
          {t('review.products.apply')}
        </Button>
      </DialogFooter>
    </>
  )
}
