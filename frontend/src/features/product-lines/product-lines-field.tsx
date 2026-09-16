import { useTranslation } from 'react-i18next'
import { Boxes, Plus, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import { PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE } from '@/features/product-categories/for-select-api'
import { ProductCategoryRootSelect } from '@/features/product-lines/product-category-root-select'
import { ProductCategoryTreeSelect } from '@/features/product-lines/product-category-tree-select'
import { useProductLinesField } from '@/features/product-lines/use-product-lines-field'
import type { ProductLine, ProductLineRow } from '@/features/product-lines/types'

export type { ProductLine, ProductLineRow }

interface ProductLinesFieldProps {
  value: ProductLineRow[]
  onChange: (rows: ProductLineRow[]) => void
  disabled?: boolean
}

/**
 * The CARD row editor (spec 0132, superseding the funzione+categoria
 * contract of spec 0057/0111): a commercial card — opportunity, project,
 * campaign, request — classifies each `product_lines` row by picking a ROOT
 * category first, then one of its `is_selectable` descendants; the effective
 * business function is derived and persisted server-side, never an input
 * here (D-3). Styled/interacted like `ManagerSlotsField`: "Add" appends an
 * empty row (full-width dashed button), each row edits its pair IN PLACE
 * (numbered chip, two selects, a trailing remove button). Rows are
 * independent (spec 0077 rev.2): only the `single`-mode row cap survives
 * (AC-041). The competence editor is a separate component,
 * `CompetenceLinesField` — it kept the funzione+categoria contract (D-4) and
 * is no longer a variant of this one. All non-render logic lives in
 * `useProductLinesField` — this component only renders it.
 */
export function ProductLinesField({ value, onChange, disabled = false }: ProductLinesFieldProps) {
  const { t } = useTranslation()
  const { addRow, removeRow, setRowRootCategory, setRowProductCategory, rootCategoryFor, canAddRow } =
    useProductLinesField({ value, onChange })
  // The category quick-create is the only one left (spec 0132 scope: the
  // function quick-create is removed, not replaced).
  const productCategoryQuickCreate = useQuickCreateAction(PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE)

  return (
    <div className="flex flex-col gap-2">
      <ul className="flex flex-col gap-2">
        {value.map((row, index) => {
          const rootCategoryId = rootCategoryFor(row)
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
                  <ProductCategoryRootSelect
                    value={rootCategoryId}
                    onChange={(id) => setRowRootCategory(index, id)}
                    disabled={disabled}
                    triggerLabel={t('productLines.rootCategory', { n: index + 1 })}
                  />
                </div>

                <div className="min-w-0 flex-1">
                  {/* Reads the category TREE, so the parent categories show
                      above the pickable ones (user directive 2026-08-03).
                      Without a root chosen there is nothing to scope it by:
                      the disabled placeholder keeps the row's two steps in
                      order, as before (AC-014). */}
                  <ProductCategoryTreeSelect
                    value={row.product_category_id}
                    onChange={(id) => setRowProductCategory(index, id)}
                    scope={{ kind: 'root', rootCategoryId }}
                    disabled={disabled}
                    action={productCategoryQuickCreate.renderAction(
                      (ref) => setRowProductCategory(index, ref.id),
                      disabled || rootCategoryId === null,
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
