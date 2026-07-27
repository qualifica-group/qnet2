import type { ICellRendererParams } from 'ag-grid-community'
import { Badge } from '@/components/ui/badge'
import { BADGE_BASE, CELL_WRAPPER, EmptyCell } from '@/features/table/cell-renderers'
import { enumLabelOf } from '@/features/config/enum-label'
import { FIELD_TYPE_ICONS } from '@/features/custom-fields/field-type-icons'
import type { CustomFieldType } from '@/features/custom-fields/types'

/** Mirrors FieldTypeRegistry::ENUM_KEY — the backend's `enumKeyFor('type')`. */
const FIELD_TYPE_ENUM_KEY = 'custom_field_type'

/**
 * `type` badge cell for the two grids that list the shared field-type
 * catalogue (attributes and custom field definitions). The generic `BadgeCell`
 * fallback is not enough here: the catalogue is config-driven (no color/icon
 * metadata comes from the backend), while every other place that names a type
 * — attribute detail, assignment editor, type picker — pairs the label with its
 * {@link FIELD_TYPE_ICONS} glyph. Label comes from `enums.custom_field_type.*`,
 * the same path the Set Filter checklist uses, read non-reactively through
 * `enumLabelOf` because AG Grid renderers cannot call hooks.
 */
export function FieldTypeBadgeCell({ value }: ICellRendererParams) {
  if (typeof value !== 'string' || value === '') {
    return <EmptyCell />
  }

  const TypeIcon = FIELD_TYPE_ICONS[value as CustomFieldType]

  return (
    <div className={CELL_WRAPPER}>
      <Badge variant="secondary" className={`${BADGE_BASE} gap-1.5`}>
        {TypeIcon ? <TypeIcon className="size-3.5" aria-hidden="true" /> : null}
        {enumLabelOf(FIELD_TYPE_ENUM_KEY, value)}
      </Badge>
    </div>
  )
}
