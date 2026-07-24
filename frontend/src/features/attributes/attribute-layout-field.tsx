import type { Control, FieldPath } from 'react-hook-form'
import { FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { AttributeControlBridge } from '@/features/attributes/attribute-control-bridge'
import { toCustomFieldDescriptor } from '@/features/attributes/effective-attribute-adapter'
import type { AttributeLayoutFormShape } from '@/features/attributes/attribute-layout-types'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

interface AttributeLayoutFieldProps<TFieldValues extends AttributeLayoutFormShape> {
  control: Control<TFieldValues>
  attribute: EffectiveAttribute
  disabled: boolean
  readOnly: boolean
}

/**
 * One `EffectiveAttribute`-backed field bound to `attribute_values.<code>`,
 * dispatched through `CUSTOM_FIELD_COMPONENT_REGISTRY` via
 * `AttributeControlBridge` — the leaf both the flat fallback and the
 * layout-grid renderer share, mirroring `ProductDynamicField`
 * (`features/products/product-dynamic-fields.tsx`) byte-for-byte in markup
 * so the flat fallback stays an exact regression match (spec 0062 AC-007),
 * generic over the host form's value shape instead of `ProductFormValues`.
 */
export function AttributeLayoutField<TFieldValues extends AttributeLayoutFormShape>({
  control,
  attribute,
  disabled,
  readOnly,
}: AttributeLayoutFieldProps<TFieldValues>) {
  const name = `attribute_values.${attribute.code}` as FieldPath<TFieldValues>
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
            readOnly={readOnly}
          />
          {attribute.help_text ? <FormDescription>{attribute.help_text}</FormDescription> : null}
          <FormMessage />
        </FormItem>
      )}
    />
  )
}
