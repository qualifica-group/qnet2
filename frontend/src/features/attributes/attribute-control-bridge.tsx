import { useFormField } from '@/components/ui/form'
import { CUSTOM_FIELD_COMPONENT_REGISTRY } from '@/features/custom-fields/field-component-registry'
import type { CustomFieldDescriptor, CustomFieldValue } from '@/features/custom-fields/types'

interface AttributeControlBridgeProps {
  descriptor: CustomFieldDescriptor
  value: CustomFieldValue
  onChange: (value: CustomFieldValue) => void
  disabled: boolean
  readOnly: boolean
}

/**
 * Resolves the accessible-error triad (frontend.md §10) via `useFormField()`
 * — one level inside `<MetaField>`'s render prop, since `<FormControl>`'s
 * automatic id injection only reaches its immediate JSX child — then
 * dispatches to `CUSTOM_FIELD_COMPONENT_REGISTRY` by `descriptor.type`.
 * Shared by every attribute-catalogue-backed form (product-categories'
 * dynamic fields today); mirrors `RequestAttributeControlBridge` in
 * `features/request-management/request-dynamic-fields.tsx`, which stays
 * untouched (CLAUDE.md hard-invariant on the Opportunity path).
 */
export function AttributeControlBridge({
  descriptor,
  value,
  onChange,
  disabled,
  readOnly,
}: AttributeControlBridgeProps) {
  const { formItemId, formDescriptionId, formMessageId, error } = useFormField()
  const FieldControl = CUSTOM_FIELD_COMPONENT_REGISTRY[descriptor.type]

  return (
    <FieldControl
      descriptor={descriptor}
      value={value}
      onChange={onChange}
      disabled={disabled}
      readOnly={readOnly}
      id={formItemId}
      describedBy={error ? `${formDescriptionId} ${formMessageId}` : formDescriptionId}
      invalid={Boolean(error)}
    />
  )
}
