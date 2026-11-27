import type { Control } from 'react-hook-form'
import { cn } from '@/lib/utils'
import { AttributeLayoutField } from '@/features/attributes/attribute-layout-field'
import { LAYOUT_GRID_GAP_CLASS, gridColsClass, itemSpanClass } from '@/features/attributes/attribute-layout-grid'
import type { AttributeLayoutFormShape, LayoutSection } from '@/features/attributes/attribute-layout-types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

/** No attribute shown read-only on its own: hoisted for a stable default reference. */
const NO_READ_ONLY_CODES: ReadonlySet<string> = new Set()

interface AttributeLayoutRowsProps<TFieldValues extends AttributeLayoutFormShape> {
  section: LayoutSection
  attributesByCode: ReadonlyMap<string, EffectiveAttribute>
  control: Control<TFieldValues>
  disabled: boolean
  readOnly: boolean
  /** Attributes shown but not editable on their own (e.g. a Commessa value owned by an integration). */
  readOnlyCodes?: ReadonlySet<string>
}

/**
 * The editable rows of one `LayoutSection`: each row its own CSS grid
 * (`section.columns` wide, collapsing per `attribute-layout-grid.ts`), each
 * item spanning its configured `width`. The caller wraps them in an
 * `@container` so the grids collapse on its own width. An item whose
 * `attribute_code` no longer resolves (stale layout) is skipped.
 */
export function AttributeLayoutRows<TFieldValues extends AttributeLayoutFormShape>({
  section,
  attributesByCode,
  control,
  disabled,
  readOnly,
  readOnlyCodes = NO_READ_ONLY_CODES,
}: AttributeLayoutRowsProps<TFieldValues>) {
  return section.rows.map((row) => (
    <div key={row.id} className={cn('grid', LAYOUT_GRID_GAP_CLASS, gridColsClass(section.columns))}>
      {row.items.map((item) => {
        const attribute = attributesByCode.get(item.attribute_code)
        if (!attribute) {
          return null
        }
        return (
          <div key={item.attribute_code} className={itemSpanClass(section.columns, item.width)}>
            <AttributeLayoutField
              control={control}
              attribute={attribute}
              disabled={disabled}
              readOnly={readOnly || readOnlyCodes.has(attribute.code)}
            />
          </div>
        )
      })}
    </div>
  ))
}
