import { useTranslation } from 'react-i18next'
import { Boxes, Plus, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import { PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE } from '@/features/product-categories/for-select-api'
import { ProductCategoryTreeSelect } from '@/features/product-lines/product-category-tree-select'
import { useCompetenceLinesField } from '@/features/product-lines/use-competence-lines-field'
import type { CompetenceLineRow, KnownProductLine } from '@/features/product-lines/types'

export type { CompetenceLineRow }

/** Stable empty default: avoids a fresh array reference (and reference-equality churn) on every render (engineering.md §10). */
const EMPTY_KNOWN_LINES: KnownProductLine[] = []

interface CompetenceLinesFieldProps {
  value: CompetenceLineRow[]
  onChange: (rows: CompetenceLineRow[]) => void
  /** Rows whose labels are already known without a fetch (edit load, in-form pickers). */
  knownLines?: KnownProductLine[]
  disabled?: boolean
}

/**
 * The person's COMPETENCE row editor (spec 0111 D-5, spec 0129, spec 0132
 * D-4): funzione-aziendale + categoria-prodotto, split out of the shared
 * `ProductLinesField` this used to be a variant of — the card contract moved
 * to a root-category pick (spec 0132) that has no meaning for a competence,
 * so the two editors are now distinct components on distinct row shapes
 * (`CompetenceLineRow` vs `ProductLineRow`). Unlike the card editor, there is
 * NO `single`-mode row cap here (spec 0111 D-5: a person legitimately covers
 * several single-mode categories) and each row carries an "all categories of
 * the function" checkbox (spec 0129 D-5). A container category
 * (`is_selectable=false`) is pickable when it matches the row's function
 * (D-6/D-7). All non-render logic lives in `useCompetenceLinesField` — this
 * component only renders it.
 */
export function CompetenceLinesField({
  value,
  onChange,
  knownLines = EMPTY_KNOWN_LINES,
  disabled = false,
}: CompetenceLinesFieldProps) {
  const { t } = useTranslation()
  const { addRow, removeRow, setRowBusinessFunction, setRowProductCategory, setRowAllCategories, businessFunctionLabel } =
    useCompetenceLinesField({ value, onChange, knownLines })
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
                    scope={{
                      kind: 'business_function',
                      businessFunctionId: row.business_function_id,
                      includeContainers: true,
                    }}
                    disabled={disabled || row.all_categories === true}
                    action={productCategoryQuickCreate.renderAction(
                      (ref) => setRowProductCategory(index, ref.id),
                      disabled || row.business_function_id === null || row.all_categories === true,
                    )}
                    triggerLabel={t('productLines.category', { n: index + 1 })}
                  />
                </div>
              </div>

              {/* D-5: "all categories of the function" — mutually exclusive
                  with a specific category pick, so the select above disables
                  and clears the moment this is checked. */}
              <label className="flex shrink-0 items-center gap-1.5 text-xs text-muted-foreground">
                <Checkbox
                  checked={row.all_categories === true}
                  onCheckedChange={(checked) => setRowAllCategories(index, checked === true)}
                  disabled={disabled || row.business_function_id === null}
                  aria-label={t('productLines.allCategories', { n: index + 1 })}
                />
                {t('productLines.allCategoriesShort')}
              </label>

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
        disabled={disabled}
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
