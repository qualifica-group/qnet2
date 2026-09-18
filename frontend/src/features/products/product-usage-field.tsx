import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { MetaField } from '@/features/authorization/MetaField'
import { useEnumOptions } from '@/features/config/use-config'
import type { ProductUsage } from '@/features/products/types'
import type { ProductFormValues } from '@/features/products/use-product-form'

interface ProductUsageFieldProps {
  control: Control<ProductFormValues>
  className?: string
}

/**
 * Where the product may be used inside an Offerta (spec 0142): two
 * independent checkboxes (Sellable -> Prodotti tab, Usable as cost -> Costi
 * tab) bound to the `usages` set. Options come from the `product_usage`
 * config enum. Like the task weekdays group, a checkbox group has no single
 * focusable root, so no `<FormControl>` wraps it.
 */
export function ProductUsageField({ control, className }: ProductUsageFieldProps) {
  const { t } = useTranslation()
  const options = useEnumOptions('product_usage')

  return (
    <MetaField
      control={control}
      name="usages"
      metaKey="usages"
      label={t('products.form.usages')}
      required
      className={className}
    >
      {({ field, disabled }) => (
        <div className="flex flex-wrap gap-4 pt-1">
          {options.map((option) => {
            const usage = option.value as ProductUsage
            const id = `product-usage-${usage.toLowerCase()}`
            return (
              <div key={usage} className="flex items-center gap-1.5">
                <Checkbox
                  id={id}
                  checked={field.value.includes(usage)}
                  disabled={disabled}
                  onCheckedChange={(checked) => {
                    field.onChange(
                      checked === true
                        ? [...field.value, usage]
                        : field.value.filter((value) => value !== usage),
                    )
                  }}
                />
                <Label htmlFor={id} className="text-xs font-normal">
                  {option.label}
                </Label>
              </div>
            )
          })}
        </div>
      )}
    </MetaField>
  )
}
