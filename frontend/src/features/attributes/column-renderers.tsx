import { DateTimeCell } from '@/features/table/cell-renderers'
import { FieldTypeBadgeCell } from '@/features/custom-fields/field-type-badge-cell'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Custom cell renderers keyed by the backend column `id`. `code`/`name` fall
 * back to the AG Grid default text cell; `type` uses the shared field-type
 * badge (icon + localized label from `enums.custom_field_type.*`), the same
 * cell the custom-fields admin grid renders. `created_at` reuses the shared
 * domain-agnostic renderer (mirrors `referentTypeColumnRenderers`).
 */
export const attributeColumnRenderers: TableRendererMap = {
  type: (params) => <FieldTypeBadgeCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
