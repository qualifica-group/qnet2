import { DateTimeCell } from '@/features/table/cell-renderers'
import { FieldTypeBadgeCell } from '@/features/custom-fields/field-type-badge-cell'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Cell renderers for the `custom-fields` admin grid. `type` uses the shared
 * field-type badge (icon + localized label from `enums.custom_field_type.*`) —
 * the same cell the attributes grid renders, since both list the one
 * config-driven type catalogue.
 */
export const customFieldColumnRenderers: TableRendererMap = {
  type: (params) => <FieldTypeBadgeCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
