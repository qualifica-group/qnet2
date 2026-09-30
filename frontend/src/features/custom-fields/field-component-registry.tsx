import type { ComponentType } from 'react'
import { TableFieldControl } from '@/features/custom-fields/components/table-field-control'
import { SCALAR_FIELD_COMPONENT_REGISTRY } from '@/features/custom-fields/scalar-field-component-registry'
import type { CustomFieldControlProps } from '@/features/custom-fields/components/custom-field-control-props'
import type { CustomFieldType } from '@/features/custom-fields/types'

export type { CustomFieldControlProps }

/**
 * The single seam that maps a backend `type` to a frontend control (spec 0021
 * OCP constraint: "1 FieldTypeHandler backend + 1 registry entry frontend,
 * zero other changes"). Adding a new custom field type means:
 *   1. a new `<X>FieldControl.tsx` implementing `CustomFieldControlProps`;
 *   2. one new entry below (or in the scalar registry for a scalar type).
 * `CustomFieldsSection` never branches on `type` itself — it only looks up
 * this map.
 */
export const CUSTOM_FIELD_COMPONENT_REGISTRY: Record<
  CustomFieldType,
  ComponentType<CustomFieldControlProps>
> = {
  ...SCALAR_FIELD_COMPONENT_REGISTRY,
  table: TableFieldControl,
}
