import type { Control } from 'react-hook-form'
import { Eye } from 'lucide-react'
import { ConfigSection } from '@/components/ui/config-section'
import { AttributeLayoutRows } from '@/features/attributes/attribute-layout-rows'
import type { AttributeLayoutFormShape, LayoutSection } from '@/features/attributes/attribute-layout-types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

interface AttributeLayoutSectionProps<TFieldValues extends AttributeLayoutFormShape> {
  section: LayoutSection
  attributesByCode: ReadonlyMap<string, EffectiveAttribute>
  control: Control<TFieldValues>
  disabled: boolean
  readOnly: boolean
  /** "Visible if: ..." line for a conditional section (configurator preview); `null` = none. */
  conditionHint?: string | null
}

/**
 * Renders one configured `LayoutSection`: a `ConfigSection` shell hosting its
 * rows (`AttributeLayoutRows`, the same grid the detail's section popup uses).
 */
export function AttributeLayoutSection<TFieldValues extends AttributeLayoutFormShape>({
  section,
  attributesByCode,
  control,
  disabled,
  readOnly,
  conditionHint = null,
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
        {conditionHint ? (
          <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
            <Eye className="size-3.5 shrink-0" aria-hidden="true" />
            {conditionHint}
          </p>
        ) : null}
        <AttributeLayoutRows
          section={section}
          attributesByCode={attributesByCode}
          control={control}
          disabled={disabled}
          readOnly={readOnly}
        />
      </div>
    </ConfigSection>
  )
}
