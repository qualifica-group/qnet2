import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { MetaField } from '@/features/authorization/MetaField'
import { VAT_RATES_FOR_SELECT_RESOURCE } from '@/features/vat-rates/for-select-api'
import type { ProductFormValues } from '@/features/products/use-product-form'

/*
 * The product's pricing and supply fields, shared by the detail's in-place
 * rows and the create draft's rows (spec 0218).
 */

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

/** "Costo standard" / "Prezzo standard": a money amount (`decimal:2` server-side). */
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
