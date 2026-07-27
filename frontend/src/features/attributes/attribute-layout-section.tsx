import type { Control } from 'react-hook-form'
import { ConfigSection } from '@/components/ui/config-section'
import { cn } from '@/lib/utils'
import { AttributeLayoutField } from '@/features/attributes/attribute-layout-field'
import { LAYOUT_GRID_GAP_CLASS, gridColsClass, itemSpanClass } from '@/features/attributes/attribute-layout-grid'
import type { AttributeLayoutFormShape, LayoutSection } from '@/features/attributes/attribute-layout-types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

interface AttributeLayoutSectionProps<TFieldValues extends AttributeLayoutFormShape> {
  section: LayoutSection
  attributesByCode: ReadonlyMap<string, EffectiveAttribute>
  control: Control<TFieldValues>
  disabled: boolean
  readOnly: boolean
}

/**
 * Renders one configured `LayoutSection`: a `ConfigSection` shell hosting its
 * `rows` — each row its own CSS grid (`section.columns` wide, collapsing per
 * `attribute-layout-grid.ts`), each item spanning its configured `width`. An
 * item whose `attribute_code` no longer resolves against `attributesByCode`
 * (stale layout referencing a removed/reassigned attribute) is skipped
 * rather than crashing the form.
 */
export function AttributeLayoutSection<TFieldValues extends AttributeLayoutFormShape>({
  section,
  attributesByCode,
  control,
  disabled,
  readOnly,
}: AttributeLayoutSectionProps<TFieldValues>) {
  return (
    <ConfigSection
      title={section.title}
      description={section.description}
      variant={section.variant}
      collapsible={section.collapsible}
      defaultCollapsed={section.default_collapsed}
    >
      {/* `@container`: the row grids collapse on THIS panel's width, not the viewport (attribute-layout-grid.ts). */}
      <div className="@container flex flex-col gap-3">
        {section.rows.map((row) => (
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
                    readOnly={readOnly}
                  />
                </div>
              )
            })}
          </div>
        ))}
      </div>
    </ConfigSection>
  )
}
