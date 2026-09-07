import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { Wallet } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { FIELD_GRID_CLASS } from '@/components/record-form/layout'
import { EMPTY_VALUE } from '@/components/record-form/record-summary'
import { Input } from '@/components/ui/input'
import { FormControl } from '@/components/ui/form'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { cn } from '@/lib/utils'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { VAT_RATES_FOR_SELECT_RESOURCE } from '@/features/vat-rates/for-select-api'
import { formatDecimal } from '@/features/products/column-renderers'
import { computeProductMargin } from '@/features/products/product-margin'
import type { ProductSelectedRelations } from '@/features/products/product-form-summary'
import type { ProductFormValues } from '@/features/products/use-product-form'

/** Filters the supplier picker's `registries` for-select to `is_supplier` records only. */
const SUPPLIER_PARAMS: Record<string, string | number> = { is_supplier: 1 }

interface ProductPricingSectionProps {
  control: Control<ProductFormValues>
  /** Hydrated relation refs of the loaded product; `null` members in create. */
  selected: ProductSelectedRelations
}

/** Formats a raw numeric field's RHF value for a controlled `<input type="number">`. */
function numberInputValue(value: number | null): string {
  return value === null ? '' : String(value)
}

/**
 * The margin, read live from the two fields right above it. Costo and prezzo
 * are entered one next to the other but their difference is the number the
 * operator is actually deciding — showing it here means it never has to be
 * computed in the head, and a negative one is flagged the moment it is typed
 * rather than after the save.
 */
function ProductMarginReadout({ control }: { control: Control<ProductFormValues> }) {
  const { t } = useTranslation()
  const cost = useWatch({ control, name: 'cost' })
  const price = useWatch({ control, name: 'price' })
  const margin = computeProductMargin(cost, price)

  return (
    <div className="flex flex-wrap items-baseline justify-between gap-2 rounded-lg border bg-muted/40 px-3 py-2">
      <span className="text-xs font-medium text-muted-foreground">{t('products.margin')}</span>
      {margin ? (
        <span className={cn('flex items-baseline gap-2', margin.amount < 0 && 'text-destructive')}>
          <span className="text-sm font-semibold tabular-nums">{formatDecimal(margin.amount)}</span>
          {margin.percent !== null ? (
            <span className="text-xs text-muted-foreground">
              {t('products.marginPercent', { percent: formatDecimal(margin.percent) })}
            </span>
          ) : null}
        </span>
      ) : (
        <span className="text-sm text-muted-foreground">{EMPTY_VALUE}</span>
      )}
    </div>
  )
}

/**
 * What the product COSTS and what it is SOLD for, plus who supplies it and at
 * which VAT rate — the four fields an operator reads together when pricing a
 * catalogue entry, closed by the live margin.
 */
export function ProductPricingSection({ control, selected }: ProductPricingSectionProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()

  const amountsVisible = fieldPermission('cost').visible && fieldPermission('price').visible
  const visible =
    fieldPermission('cost').visible ||
    fieldPermission('price').visible ||
    fieldPermission('vat_rate_id').visible ||
    fieldPermission('supplier_id').visible

  if (!visible) {
    return null
  }

  return (
    <FormSection
      icon={Wallet}
      title={t('products.form.sections.pricing.title')}
      description={t('products.form.sections.pricing.description')}
    >
      <div className={FIELD_GRID_CLASS}>
        <MetaField control={control} name="cost" metaKey="cost" label={t('products.form.cost')}>
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input
                type="number"
                step="0.01"
                inputMode="decimal"
                disabled={disabled}
                readOnly={readOnly}
                value={numberInputValue(field.value)}
                onChange={(event) =>
                  field.onChange(event.target.value === '' ? null : Number(event.target.value))
                }
                onBlur={field.onBlur}
                name={field.name}
                ref={field.ref}
              />
            </FormControl>
          )}
        </MetaField>

        <MetaField control={control} name="price" metaKey="price" label={t('products.form.price')}>
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input
                type="number"
                step="0.01"
                inputMode="decimal"
                disabled={disabled}
                readOnly={readOnly}
                value={numberInputValue(field.value)}
                onChange={(event) =>
                  field.onChange(event.target.value === '' ? null : Number(event.target.value))
                }
                onBlur={field.onBlur}
                name={field.name}
                ref={field.ref}
              />
            </FormControl>
          )}
        </MetaField>

        <RelationSelectField
          control={control}
          name="vat_rate_id"
          metaKey="vat_rate_id"
          label={t('products.form.vatRate')}
          resource={VAT_RATES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('products.form.vatRateSearch')}
          selected={selected.vatRate}
          placeholder={t('products.form.vatRatePlaceholder')}
          emptyLabel={t('products.form.vatRateEmpty')}
          errorLabel={t('products.form.vatRateError')}
          clearLabel={t('common.clear')}
          retryLabel={t('common.retry')}
        />

        <RelationSelectField
          control={control}
          name="supplier_id"
          metaKey="supplier_id"
          label={t('products.form.supplier')}
          resource={REGISTRIES_FOR_SELECT_RESOURCE}
          params={SUPPLIER_PARAMS}
          searchPlaceholder={t('products.form.supplierSearch')}
          selected={selected.supplier}
          placeholder={t('products.form.supplierPlaceholder')}
          emptyLabel={t('products.form.supplierEmpty')}
          errorLabel={t('products.form.supplierError')}
          clearLabel={t('common.clear')}
          retryLabel={t('common.retry')}
        />
      </div>

      {/* Only where both amounts are readable: a margin computed from one
          visible field and one withheld one would be a leak, not a hint. */}
      {amountsVisible ? <ProductMarginReadout control={control} /> : null}
    </FormSection>
  )
}
