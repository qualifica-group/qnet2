import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Loader2, SlidersHorizontal } from 'lucide-react'
import type { Control, Path } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { AttributeLayoutRenderer } from '@/features/attributes/attribute-layout-renderer'
import type { AttributeLayoutFormShape, LayoutBlob } from '@/features/attributes/attribute-layout-types'
import { MetaField } from '@/features/authorization/MetaField'
import { toEffectiveAttribute } from '@/features/request-management/applicable-attribute-adapter'
import type { ApplicableAttributeSummary } from '@/features/quotes/types'

interface QuoteDynamicFieldsSectionProps<TFieldValues extends AttributeLayoutFormShape> {
  control: Control<TFieldValues>
  /** Union, dedup by `code`, of the effective Attributes of the resolved product categories. */
  attributes: ApplicableAttributeSummary[]
  /** The merged, multi-category layout (spec 0062); `null` -> flat. */
  layout: LayoutBlob | null
  /** True while the resolution for the currently picked products is in flight. */
  isLoading: boolean
  className?: string
}

/**
 * "Informazioni aggiuntive" on the quote form (spec 0084): the SAME
 * module-agnostic `AttributeLayoutRenderer` the product card drives, fed the
 * set the OFFER LINES' product categories resolve to — sectioned when those
 * categories carry a configured layout (spec 0062), flat otherwise.
 *
 * D-5 (user directive 2026-08-06) governs both placement and trigger:
 *
 * - Placement is BELOW the line tabs (so, below Costi) and above the economic
 *   summary. Not inside the Costi tab: the set is resolved from the OFFER
 *   lines' products, so nesting it there would bind the section to the wrong
 *   card.
 * - The trigger is picking a PRODUCT. Until a line carries one there is no
 *   category, hence no attribute, hence nothing to show. That case is decided
 *   by the CALLER, which simply does not mount this component: on a brand-new
 *   quote the operator has not asked for this block yet, and a "no additional
 *   fields" placeholder sitting under the totals would read as a defect.
 *
 * The empty-state card below is therefore reserved for the case that IS
 * informative: products ARE picked, resolution finished, and those categories
 * genuinely configure no attribute.
 *
 * The whole block is gated by the single `attribute_values` field permission:
 * `MetaField` wraps the renderer's output rather than each leaf field. A
 * configured layout's sections already carry their own card, so that branch
 * mounts the gate directly — nesting it inside a `FormSection` would stack two
 * cards on the same surface (ui-design.md §1-bis).
 */
export function QuoteDynamicFieldsSection<TFieldValues extends AttributeLayoutFormShape>({
  control,
  attributes,
  layout,
  isLoading,
  className,
}: QuoteDynamicFieldsSectionProps<TFieldValues>) {
  const { t } = useTranslation()
  const title = t('quotes.form.sections.dynamicFields.title')
  // `AttributeLayoutFormShape` guarantees the key on every accepted form, but
  // TS cannot narrow a literal to `Path<TFieldValues>` through the generic —
  // the same cast `useRequestWorkForm` already applies to its own field paths.
  const attributeValuesField = 'attribute_values' as Path<TFieldValues>

  const effectiveAttributes = useMemo(
    () => attributes.map((attribute) => toEffectiveAttribute(attribute, 'quote')),
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
          {t('quotes.form.sections.dynamicFields.empty')}
        </p>
      </FormSection>
    )
  }

  const gatedRenderer = (
    <MetaField control={control} name={attributeValuesField} metaKey="attribute_values" label={title}>
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
