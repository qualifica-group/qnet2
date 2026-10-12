import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { MetaField } from '@/features/authorization/MetaField'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { VAT_RATES_FOR_SELECT_RESOURCE } from '@/features/vat-rates/for-select-api'
import { productPricingVisibility } from '@/features/products/product-pricing-visibility'
import { ProductUsageField } from '@/features/products/product-usage-field'
import type { ProductUsage } from '@/features/products/types'
import type { ProductFormValues } from '@/features/products/use-product-form'

/*
 * The product's pricing and supply fields, shared by the detail's in-place
 * rows and the create draft's rows (spec 0218).
 */

/** Filters the supplier picker's `registries` for-select to `is_supplier` records only. */
const SUPPLIER_PARAMS: Record<string, string | number> = { is_supplier: 1 }

interface FieldProps {
  control: Control<ProductFormValues>
}

interface SelectedFieldProps extends FieldProps {
  selected: RelationFieldRef | null
}

/** Formats a raw numeric field's RHF value for a controlled `<input type="number">`. */
function numberInputValue(value: number | null): string {
  return value === null ? '' : String(value)
}

/** "Costo" / "Prezzo": a money amount (`decimal:2` server-side). */
export function ProductAmountField({ control, name }: FieldProps & { name: 'cost' | 'price' }) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name={name} metaKey={name} label={t(`products.form.${name}`)}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input
            type="number"
            step="0.01"
            inputMode="decimal"
            disabled={disabled}
            readOnly={readOnly}
            value={numberInputValue(field.value)}
            onChange={(event) => field.onChange(event.target.value === '' ? null : Number(event.target.value))}
            onBlur={field.onBlur}
            name={field.name}
            ref={field.ref}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

interface ProductUsagesEditorProps extends FieldProps {
  /** Detail only: the persisted usages; a usage switched on here asks its amount right away (spec 0218 D-4). */
  persistedUsages: ProductUsage[] | null
}

/**
 * "Utilizzi" (spec 0142): the two checkboxes, plus — on the detail — the
 * amount a newly enabled usage makes required (spec 0191), filled in the same
 * editor so that very save is not refused for a field nobody could see.
 */
export function ProductUsagesEditor({ control, persistedUsages }: ProductUsagesEditorProps) {
  const usages = useWatch({ control, name: 'usages' })
  const next = productPricingVisibility(usages)
  const before = productPricingVisibility(persistedUsages)
  const asksCost = persistedUsages !== null && next.cost && !before.cost
  const asksPrice = persistedUsages !== null && next.price && !before.price

  return (
    <div className="flex min-w-0 flex-col gap-3">
      <ProductUsageField control={control} />
      {asksCost ? <ProductAmountField control={control} name="cost" /> : null}
      {asksPrice ? <ProductAmountField control={control} name="price" /> : null}
    </div>
  )
}

/** "Aliquota IVA". */
export function ProductVatRateField({ control, selected }: SelectedFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="vat_rate_id"
      metaKey="vat_rate_id"
      label={t('products.form.vatRate')}
      resource={VAT_RATES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('products.form.vatRateSearch')}
      selected={selected}
      placeholder={t('products.form.vatRatePlaceholder')}
      emptyLabel={t('products.form.vatRateEmpty')}
      errorLabel={t('products.form.vatRateError')}
      clearLabel={t('common.clear')}
      retryLabel={t('common.retry')}
    />
  )
}

/** "Fornitore": an anagrafica flagged as supplier. */
export function ProductSupplierField({ control, selected }: SelectedFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="supplier_id"
      metaKey="supplier_id"
      label={t('products.form.supplier')}
      resource={REGISTRIES_FOR_SELECT_RESOURCE}
      params={SUPPLIER_PARAMS}
      searchPlaceholder={t('products.form.supplierSearch')}
      selected={selected}
      placeholder={t('products.form.supplierPlaceholder')}
      emptyLabel={t('products.form.supplierEmpty')}
      errorLabel={t('products.form.supplierError')}
      clearLabel={t('common.clear')}
      retryLabel={t('common.retry')}
    />
  )
}
