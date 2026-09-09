import { useTranslation } from 'react-i18next'
import { Boxes, Plus, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import { PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE } from '@/features/product-categories/for-select-api'
import { ProductCategoryTreeSelect } from '@/features/product-lines/product-category-tree-select'
import { useProductLinesField } from '@/features/product-lines/use-product-lines-field'
import type { ProductLine, ProductLineRow } from '@/features/product-lines/types'

export type { ProductLine, ProductLineRow }

/** Stable empty default: avoids a fresh array reference (and reference-equality churn) on every render (engineering.md §10). */
const EMPTY_KNOWN_LINES: ProductLine[] = []

interface ProductLinesFieldProps {
  value: ProductLineRow[]
  onChange: (rows: ProductLineRow[]) => void
  /** Rows whose labels are already known without a fetch (edit load, from-lead prefill, in-form pickers). */
  knownLines?: ProductLine[]
  disabled?: boolean
  /**
   * Spec 0111 D-5: `false` where the row set is a person's competence, not a
   * commercial card — there is no "one deal, one single-mode line" invariant to
   * enforce there. Defaults to `true`: opportunity, project, campaign and
   * request forms keep capping the card (AC-041).
   */
  enforceManagementModeCap?: boolean
}

/**
 * The business-function + product-category row editor (spec 0057, frozen
 * contract): shared by the opportunity form and the request-management
 * create form (originally `OpportunityProductLinesField`, spec 0040
 * amendment rev.3 AC-106/107). Styled/interacted like `ManagerSlotsField`:
 * "Add" appends an empty row (full-width dashed button), each row edits its
 * pair IN PLACE (numbered chip, two selects, a trailing remove button), the
 * category select is scoped to the row's own function and disabled until it
 * is chosen. Every row is independent: spec 0077 rev.2 (user directive
 * 2026-08-31) revoked INV-1/INV-2, so the function of a row after the first
 * is neither prefilled, nor locked, nor confined to the first row's branch —
 * only the `single`-mode row cap survives (AC-041), and even that one is
 * opt-out (`enforceManagementModeCap`, spec 0111 D-5). All non-render logic
 * (label resolution) lives in `useProductLinesField` — this component only
 * renders it.
 *
 * The two selects read DIFFERENT channels on purpose: the function is a flat,
 * server-paginated `for-select`, the category is the structural TREE
 * (`ProductCategoryTreeSelect`, user directive 2026-08-03) so its parents are
 * listed with it — disabled where they may not be picked.
 */
export function ProductLinesField({
  value,
  onChange,
  knownLines = EMPTY_KNOWN_LINES,
  disabled = false,
  enforceManagementModeCap = true,
}: ProductLinesFieldProps) {
  const { t } = useTranslation()
  const {
    addRow,
    removeRow,
    setRowBusinessFunction,
    setRowProductCategory,
    businessFunctionLabel,
    canAddRow,
  } = useProductLinesField({ value, onChange, knownLines, enforceManagementModeCap })
  // One quick-create wiring per resource, shared by every row: the refs it
  // tracks are matched by id, so a function created from row 2 also labels
  // row 5 if picked there (spec 0028).
  const businessFunctionQuickCreate = useQuickCreateAction(BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE)
  const productCategoryQuickCreate = useQuickCreateAction(PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE)

  const selectLabels = {
    placeholder: t('productLines.selectPlaceholder'),
    empty: t('productLines.selectEmpty'),
    error: t('productLines.selectError'),
    clearLabel: t('common.clear'),
    retry: t('common.retry'),
  }

  return (
    <div className="flex flex-col gap-2">
      <ul className="flex flex-col gap-2">
        {value.map((row, index) => {
          // A just-created record has no label yet in either source, so its
          // own ref answers before the `#id` fallback (spec 0028 AC-006).
          const businessFunctionSelected =
            row.business_function_id !== null
              ? businessFunctionQuickCreate.selectedItemFor(row.business_function_id) ?? {
                  id: row.business_function_id,
                  label: businessFunctionLabel(row.business_function_id) ?? `#${row.business_function_id}`,
                }
              : null
          return (
            // The row's identity IS its position, so the index is the correct key (mirrors ManagerSlotsField).
            <li key={index} className="flex items-center gap-2">
              <span
                className="flex w-9 shrink-0 items-center gap-1 text-xs font-semibold text-muted-foreground"
                title={t('productLines.rowLabel', { n: index + 1 })}
              >
                <Boxes aria-hidden="true" className="size-3.5" />
                {index + 1}
              </span>

              <div className="flex min-w-0 flex-1 gap-2">
                <div className="min-w-0 flex-1">
                  <AsyncPaginatedSelect
                    resource={BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE}
                    value={row.business_function_id}
                    onChange={(id) => setRowBusinessFunction(index, id)}
                    selectedItem={businessFunctionSelected}
                    action={businessFunctionQuickCreate.renderAction(
                      (ref) => setRowBusinessFunction(index, ref.id),
                      disabled,
                    )}
                    disabled={disabled}
                    labels={{
                      ...selectLabels,
                      searchPlaceholder: t('productLines.businessFunctionSearch'),
                      triggerLabel: t('productLines.businessFunction', { n: index + 1 }),
                    }}
                  />
                </div>

                <div className="min-w-0 flex-1">
                  {/* Reads the category TREE, so the parent categories show
                      above the pickable ones (user directive 2026-08-03).
                      Without a function chosen there is nothing to scope it
                      by: the disabled placeholder keeps the row's two steps
                      in order, as before. */}
                  <ProductCategoryTreeSelect
                    value={row.product_category_id}
                    onChange={(id) => setRowProductCategory(index, id)}
                    businessFunctionId={row.business_function_id}
                    disabled={disabled}
                    action={productCategoryQuickCreate.renderAction(
                      (ref) => setRowProductCategory(index, ref.id),
                      disabled || row.business_function_id === null,
                    )}
                    triggerLabel={t('productLines.category', { n: index + 1 })}
                  />
                </div>
              </div>

              <div className="flex shrink-0 gap-1">
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('productLines.remove')}
                  disabled={disabled}
                  onClick={() => removeRow(index)}
                >
                  <Trash2 aria-hidden="true" />
                </Button>
              </div>
            </li>
          )
        })}
      </ul>

      <Button
        type="button"
        variant="outline"
        size="sm"
        disabled={disabled || !canAddRow}
        onClick={addRow}
        className="w-full justify-center border-dashed text-muted-foreground hover:border-solid hover:text-foreground"
      >
        <Plus aria-hidden="true" className="size-3.5" />
        {t('productLines.add')}
      </Button>

      <p className="text-xs text-muted-foreground">{t('productLines.hint')}</p>
    </div>
  )
}
