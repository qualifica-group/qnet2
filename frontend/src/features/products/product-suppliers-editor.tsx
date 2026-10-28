import { useEffect, useId } from 'react'
import { useTranslation } from 'react-i18next'
import { useFormState, useWatch, type Control } from 'react-hook-form'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { RadioGroup } from '@/components/ui/radio-group'
import { MetaField } from '@/features/authorization/MetaField'
import {
  addSupplierRow,
  MAX_PRODUCT_SUPPLIERS,
  patchSupplierRow,
  removeSupplierRow,
  setDefaultSupplierRow,
  type ProductSupplierRow,
} from '@/features/products/product-supplier-rows'
import { SupplierEditorRow, type SupplierRowErrors } from '@/features/products/product-supplier-editor-row'
import { SUPPLIER_EDITOR_HEADER_GRID } from '@/features/products/product-suppliers-layout'
import { useSupplierCommissions } from '@/features/products/use-supplier-commissions'
import type { ProductFormValues } from '@/features/products/use-product-form'

function EditorHeader() {
  const { t } = useTranslation()
  return (
    <div className="hidden border-b bg-muted/40 px-3 py-1.5 @3xl:block" aria-hidden="true">
      <div className={`${SUPPLIER_EDITOR_HEADER_GRID} text-[11px] font-medium tracking-wide text-muted-foreground uppercase`}>
        <span>{t('products.form.suppliers.supplier')}</span>
        <span className="text-right">{t('products.form.suppliers.price')}</span>
        <span className="text-right">{t('products.form.suppliers.cost')}</span>
        <span className="text-right">{t('products.form.suppliers.margin')}</span>
        <span className="text-center">{t('products.form.suppliers.default')}</span>
        <span />
      </div>
    </div>
  )
}

interface SuppliersListProps {
  control: Control<ProductFormValues>
  /** The saved product the commissions are looked up on; null while creating (its category is used). */
  productId: number | null
  rows: ProductSupplierRow[]
  disabled: boolean
  onChange: (rows: ProductSupplierRow[]) => void
}

function SuppliersList({ control, productId, rows, disabled, onChange }: SuppliersListProps) {
  const { t } = useTranslation()
  const rowId = useId()
  const categoryId = useWatch({ control, name: 'category_id' })
  const { errors } = useFormState({ control, name: 'suppliers' })
  const rowErrors = errors.suppliers as SupplierRowErrors[] | undefined
  const takenIds = new Set(rows.flatMap((row) => (row.supplier_id !== null ? [row.supplier_id] : [])))
  const defaultIndex = rows.findIndex((row) => row.is_default)
  const { commissionOf } = useSupplierCommissions({ productId, categoryId, supplierIds: [...takenIds] })
  const needsZeroCost = rows.some((row) => commissionOf(row.supplier_id) !== null && row.cost !== 0)

  // A supplier that earns a commission carries no cost (D-22): the form shows the 0 the server saves.
  useEffect(() => {
    if (needsZeroCost) {
      onChange(rows.map((row) => (commissionOf(row.supplier_id) !== null ? { ...row, cost: 0 } : row)))
    }
    // `rows`, `commissionOf` and `onChange` are fresh per render; the flag is the real trigger.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [needsZeroCost])

  return (
    <div className="@container flex min-w-0 flex-col overflow-hidden rounded-lg border">
      {rows.length > 0 ? <EditorHeader /> : null}
      <RadioGroup
        value={defaultIndex >= 0 ? String(defaultIndex) : ''}
        onValueChange={(value) => onChange(setDefaultSupplierRow(rows, Number(value)))}
        className="gap-0 divide-y"
      >
        {rows.map((row, index) => (
          <SupplierEditorRow
            // Rows have no stable id before they are saved; position is their identity.
            key={index}
            n={index + 1}
            row={row}
            takenIds={takenIds}
            commission={commissionOf(row.supplier_id)}
            disabled={disabled}
            error={rowErrors?.[index]}
            rowId={`${rowId}-${index}`}
            onPatch={(patch) => onChange(patchSupplierRow(rows, index, patch))}
            onRemove={() => onChange(removeSupplierRow(rows, index))}
          />
        ))}
      </RadioGroup>
      <div className={rows.length > 0 ? 'border-t bg-muted/20 px-3 py-2' : 'px-3 py-3'}>
        {rows.length === 0 ? <p className="mb-2 text-xs text-muted-foreground">{t('products.form.suppliers.emptyHint')}</p> : null}
        <Button
          type="button"
          variant="secondary"
          size="xs"
          disabled={disabled || rows.length >= MAX_PRODUCT_SUPPLIERS}
          onClick={() => onChange(addSupplierRow(rows))}
        >
          <Plus aria-hidden="true" />
          {t('products.form.suppliers.add')}
        </Button>
      </div>
    </div>
  )
}

interface ProductSuppliersFieldProps {
  control: Control<ProductFormValues>
  productId: number | null
}

/**
 * "Fornitori" (spec 0320): each row picks a supplier registry, its price and cost (always both, rev.3 D-20)
 * and whether it is the default; exactly one default (a lone row is it
 * automatically). A supplier with a commission has its cost locked at 0 (D-22).
 * Gated by the `suppliers` field permission.
 */
export function ProductSuppliersField({ control, productId }: ProductSuppliersFieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name="suppliers" metaKey="suppliers" label={t('products.form.suppliers.title')}>
      {({ field, disabled }) => (
        <SuppliersList control={control} productId={productId} rows={field.value} disabled={disabled} onChange={field.onChange} />
      )}
    </MetaField>
  )
}
