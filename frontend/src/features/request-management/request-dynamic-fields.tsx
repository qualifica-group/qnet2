import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { SlidersHorizontal } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { AttributeLayoutRenderer } from '@/features/attributes/attribute-layout-renderer'
import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'
import { MetaField } from '@/features/authorization/MetaField'
import { toEffectiveAttribute } from '@/features/request-management/applicable-attribute-adapter'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'
import type { ApplicableAttribute } from '@/features/request-management/types'

interface RequestDynamicFieldsProps {
  control: Control<RequestWorkFormValues>
  /** Union, dedup by `code`, of the effective Attributes of every product line (spec 0049). */
  attributes: ApplicableAttribute[]
  /** The merged, multi-category resolved layout for this panel (spec 0062); `null`/absent -> flat. */
  layout?: LayoutBlob | null
}

/**
 * The dynamic fields section: rendered through the module-agnostic
 * `AttributeLayoutRenderer` (spec 0062), sectioned when the opportunity's
 * categories carry a configured layout, flat (one control per attribute,
 * AC-007) otherwise. The whole block stays gated by the SAME single
 * `attribute_values` permission key every field used individually before
 * (AC-063): `<MetaField>` now wraps the renderer's output directly instead of
 * each leaf field, mirroring `RequestProductsOfInterest`'s own block-level
 * `<MetaField>` around `<ProductsOfInterestField>`. The `FormSection` icon/
 * title chrome only wraps the empty state and the flat fallback (byte-for-byte
 * the pre-0062 markup, AC-007): a configured layout's sections already carry
 * their own card (`ConfigSection`, `variant:'default'` = the same `bg-card` as
 * `FormSection`), so nesting one inside the other would stack two cards on
 * the same surface (ui-design.md §1-bis) — that branch mounts the gate
 * directly instead.
 */
export function RequestDynamicFields({ control, attributes, layout = null }: RequestDynamicFieldsProps) {
  const { t } = useTranslation()
  const title = t('requestManagement.workPanel.dynamicFields.title', { defaultValue: 'Additional information' })

  const effectiveAttributes = useMemo(() => attributes.map((attribute) => toEffectiveAttribute(attribute)), [
    attributes,
  ])

  if (attributes.length === 0) {
    return (
      <FormSection icon={SlidersHorizontal} title={title}>
        <p className="text-sm text-muted-foreground">
          {t('requestManagement.workPanel.dynamicFields.empty', {
            defaultValue: 'No additional fields for this opportunity.',
          })}
        </p>
      </FormSection>
    )
  }

  const gatedRenderer = (
    <MetaField control={control} name="attribute_values" metaKey="attribute_values" label={title}>
      {({ disabled, readOnly }) => (
        <AttributeLayoutRenderer
          layout={layout}
          attributes={effectiveAttributes}
          control={control}
          mode="edit"
          disabled={disabled}
          readOnly={readOnly}
        />
      )}
    </MetaField>
  )

  if (layout && layout.sections.length > 0) {
    return gatedRenderer
  }

  return (
    <FormSection icon={SlidersHorizontal} title={title}>
      {gatedRenderer}
    </FormSection>
  )
}
