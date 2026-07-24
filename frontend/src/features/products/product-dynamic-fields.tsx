import { useTranslation } from 'react-i18next'
import { SlidersHorizontal } from 'lucide-react'
import type { Control, FieldPath } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { Skeleton } from '@/components/ui/skeleton'
import { FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { AttributeControlBridge } from '@/features/attributes/attribute-control-bridge'
import { toCustomFieldDescriptor } from '@/features/attributes/effective-attribute-adapter'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { EffectiveAttribute } from '@/features/product-categories/types'
import type { ProductFormValues } from '@/features/products/use-product-form'

interface ProductDynamicFieldsProps {
  control: Control<ProductFormValues>
  /** The selected category's PRODUCT-context effective attributes (own + inherited), spec 0061. */
  attributes: EffectiveAttribute[]
  isLoading: boolean
  /**
   * Resource-level gate: attribute values are authorized at the
   * `products.update`/`products.create` ability, not per field (mirrors
   * `useProductFormMeta`'s documented design — there is no per-attribute
   * entry in `/meta/products`, unlike the generic fields above it).
   */
  disabled: boolean
}

/**
 * The product form's dynamic-fields section: one control per PRODUCT-context
 * effective attribute of the selected category (spec 0061), dispatched by
 * `type` through `CUSTOM_FIELD_COMPONENT_REGISTRY` via `toCustomFieldDescriptor`
 * — mirrors `RequestDynamicFields`'s bridge for the Opportunity path exactly,
 * without touching that file. Empty (with a hint) until a category is picked
 * or when the picked category carries no product attribute.
 */
export function ProductDynamicFields({ control, attributes, isLoading, disabled }: ProductDynamicFieldsProps) {
  const { t } = useTranslation()
  const title = t('products.form.dynamicFields.title')

  if (isLoading) {
    return (
      <FormSection icon={SlidersHorizontal} title={title}>
        <Skeleton className="h-9 w-full" />
      </FormSection>
    )
  }

  if (attributes.length === 0) {
    return (
      <FormSection icon={SlidersHorizontal} title={title}>
        <p className="text-sm text-muted-foreground">{t('products.form.dynamicFields.empty')}</p>
      </FormSection>
    )
  }

  const sorted = [...attributes].sort((a, b) => a.sort_order - b.sort_order)

  return (
    <FormSection icon={SlidersHorizontal} title={title}>
      {sorted.map((attribute) => (
        <ProductDynamicField key={attribute.code} control={control} attribute={attribute} disabled={disabled} />
      ))}
    </FormSection>
  )
}

interface ProductDynamicFieldProps {
  control: Control<ProductFormValues>
  attribute: EffectiveAttribute
  disabled: boolean
}

/** One Attribute-backed field, rendered via the registry bridge; required marker follows the attribute's own `is_required`. */
function ProductDynamicField({ control, attribute, disabled }: ProductDynamicFieldProps) {
  const name = `attribute_values.${attribute.code}` as FieldPath<ProductFormValues>
  const descriptor = toCustomFieldDescriptor(attribute)

  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem>
          <FormLabel required={attribute.is_required}>{attribute.name}</FormLabel>
          <AttributeControlBridge
            descriptor={descriptor}
            value={field.value as CustomFieldValue}
            onChange={field.onChange as (value: CustomFieldValue) => void}
            disabled={disabled}
            readOnly={false}
          />
          {attribute.help_text ? <FormDescription>{attribute.help_text}</FormDescription> : null}
          <FormMessage />
        </FormItem>
      )}
    />
  )
}
