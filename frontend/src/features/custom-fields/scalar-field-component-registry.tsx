import type { ComponentType } from 'react'
import { BooleanFieldControl } from '@/features/custom-fields/components/boolean-field-control'
import { EnumFieldControl } from '@/features/custom-fields/components/enum-field-control'
import { NumberFieldControl } from '@/features/custom-fields/components/number-field-control'
import { RelationFieldControl } from '@/features/custom-fields/components/relation-field-control'
import { TextFieldControl } from '@/features/custom-fields/components/text-field-control'
import { TextareaFieldControl } from '@/features/custom-fields/components/textarea-field-control'
import { createNativeInputFieldControl } from '@/features/custom-fields/components/native-input-field-control'
import type { CustomFieldControlProps } from '@/features/custom-fields/components/custom-field-control-props'
import type { CustomFieldType } from '@/features/custom-fields/types'

/**
 * The scalar half of the type -> control map. Split from
 * `field-component-registry.tsx` so `TableFieldControl` can render its cells
 * through it without an import cycle (the main registry imports the table
 * control, which would otherwise import the main registry back).
 */
export const SCALAR_FIELD_COMPONENT_REGISTRY: Record<
  Exclude<CustomFieldType, 'table'>,
  ComponentType<CustomFieldControlProps>
> = {
  text: TextFieldControl,
  textarea: TextareaFieldControl,
  integer: NumberFieldControl,
  decimal: NumberFieldControl,
  boolean: BooleanFieldControl,
  enum: EnumFieldControl,
  relation: RelationFieldControl,
  date: createNativeInputFieldControl('date'),
  datetime: createNativeInputFieldControl('datetime-local'),
  time: createNativeInputFieldControl('time'),
  email: createNativeInputFieldControl('email'),
  url: createNativeInputFieldControl('url'),
  color: createNativeInputFieldControl('color'),
}
