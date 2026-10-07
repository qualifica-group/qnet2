import { useWatch, type UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { FormControl } from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { MetaField } from '@/features/authorization/MetaField'
import type { ProductTypologyFormValues } from '@/features/product-typologies/use-product-typology-form'
import {
  SUPPLIER_COMMISSION_DIRECTIONS,
  type SupplierCommissionDirection,
} from '@/features/product-typologies/types'

interface SupplierCommissionFieldsProps {
  form: UseFormReturn<ProductTypologyFormValues>
}

/**
 * Supplier commission settings of a typology (spec 0202 D-2): the on/off
 * switch and, only while it is on, the mandatory direction. Switching off
 * resets the direction to null so the payload never carries a stale value.
 */
export function SupplierCommissionFields({ form }: SupplierCommissionFieldsProps) {
  const { t } = useTranslation()
  const enabled = useWatch({ control: form.control, name: 'supplier_commission_enabled' })

  return (
    <>
      <MetaField
        control={form.control}
        name="supplier_commission_enabled"
        metaKey="supplier_commission_enabled"
        label={t('productTypologies.form.supplierCommissionEnabled')}
      >
        {({ field, disabled }) => (
          <FormControl>
            <Switch
              checked={field.value}
              onCheckedChange={(next) => {
                field.onChange(next)
                if (!next) {
                  form.setValue('supplier_commission_direction', null, { shouldValidate: true })
                }
              }}
              disabled={disabled}
            />
          </FormControl>
        )}
      </MetaField>

      {enabled ? (
        <MetaField
          control={form.control}
          name="supplier_commission_direction"
          metaKey="supplier_commission_direction"
          label={t('productTypologies.form.supplierCommissionDirection')}
          hint={t('productTypologies.form.hints.supplierCommissionDirection')}
          required
        >
          {({ field, disabled }) => (
            <Select
              value={field.value ?? ''}
              onValueChange={(next) => field.onChange(next as SupplierCommissionDirection)}
              disabled={disabled}
            >
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder={t('productTypologies.form.supplierCommissionDirectionPlaceholder')} />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                {SUPPLIER_COMMISSION_DIRECTIONS.map((direction) => (
                  <SelectItem key={direction} value={direction}>
                    {t(`productTypologies.supplierCommissionDirection.${direction}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        </MetaField>
      ) : null}
    </>
  )
}
