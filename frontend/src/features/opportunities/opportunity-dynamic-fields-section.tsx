import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Loader2, SlidersHorizontal } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { AttributeLayoutRenderer } from '@/features/attributes/attribute-layout-renderer'
import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'
import { MetaField } from '@/features/authorization/MetaField'
import { toEffectiveAttribute } from '@/features/request-management/applicable-attribute-adapter'
import type { ApplicableAttributeSummary } from '@/features/opportunities/types'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

interface OpportunityDynamicFieldsSectionProps {
  control: Control<OpportunityFormValues>
  /** Union, dedup by `code`, of the effective Attributes of every product line (spec 0049). */
  attributes: ApplicableAttributeSummary[]
  /** The merged, multi-category layout (spec 0062); `null` -> flat. */
  layout: LayoutBlob | null
  /** True while the first resolution for the current categories is in flight (create only). */
  isLoading: boolean
  className?: string
}

/**
 * "Informazioni aggiuntive" on the opportunity form (user directive
 * 2026-08-05, "come sta in gestione richieste"): the SAME module-agnostic
 * `AttributeLayoutRenderer` the work panel and the request create form drive,
 * fed the set the opportunity's product lines resolve to — sectioned when the
 * categories carry a configured layout (spec 0062), flat otherwise.
 *
 * The whole block is gated by the single `attribute_values` field permission
 * (as in the panel, AC-063): `MetaField` wraps the renderer's output rather
 * than each leaf field. A configured layout's sections already carry their own
 * card, so that branch mounts the gate directly — nesting it inside a
 * `FormSection` would stack two cards on the same surface (ui-design.md
 * §1-bis).
 *
 * With no applicable attribute the card still holds its slot and says so,
 * rather than vanishing: on an opportunity whose categories carry none, "no
 * additional fields" is the answer — except while the create form is still
 * resolving them, where the honest state is "loading", not "none".
 */
export function OpportunityDynamicFieldsSection({
  control,
  attributes,
  layout,
  isLoading,
  className,
}: OpportunityDynamicFieldsSectionProps) {
  const { t } = useTranslation()
  const title = t('opportunities.form.sections.dynamicFields.title')

  const effectiveAttributes = useMemo(
    () => attributes.map((attribute) => toEffectiveAttribute(attribute)),
    [attributes],
  )

  if (isLoading) {
    return (
      <FormSection icon={SlidersHorizontal} title={title} className={className}>
        <p className="flex items-center gap-2 text-sm text-muted-foreground">
          <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
          {t('common.loading')}
        </p>
      </FormSection>
    )
  }

  if (attributes.length === 0) {
    return (
      <FormSection icon={SlidersHorizontal} title={title} className={className}>
        <p className="text-sm text-muted-foreground">
          {t('opportunities.form.sections.dynamicFields.empty')}
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
    return <div className={className}>{gatedRenderer}</div>
  }

  return (
    <FormSection icon={SlidersHorizontal} title={title} className={className}>
      {gatedRenderer}
    </FormSection>
  )
}
