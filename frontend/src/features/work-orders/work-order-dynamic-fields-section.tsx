import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Loader2, SlidersHorizontal } from 'lucide-react'
import type { Control, Path } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { AttributeLayoutRenderer } from '@/features/attributes/attribute-layout-renderer'
import type { AttributeLayoutFormShape, LayoutBlob } from '@/features/attributes/attribute-layout-types'
import { MetaField } from '@/features/authorization/MetaField'
import { toEffectiveAttribute } from '@/features/request-management/applicable-attribute-adapter'
import type { ApplicableAttributeSummary } from '@/features/work-orders/types'

interface WorkOrderDynamicFieldsSectionProps<TFieldValues extends AttributeLayoutFormShape> {
  control: Control<TFieldValues>
  /** Union, dedup by `code`, of the effective Attributes of the resolved product categories. */
  attributes: ApplicableAttributeSummary[]
  /** The merged, multi-category layout (spec 0062); `null` -> flat. */
  layout: LayoutBlob | null
  /** True while the resolution for the currently picked quote lines is in flight. */
  isLoading: boolean
  className?: string
}

/**
 * "Informazioni aggiuntive" on the work order form (spec 0098): the direct
 * twin of `QuoteDynamicFieldsSection` (spec 0084) — the SAME module-agnostic
 * `AttributeLayoutRenderer` the Offerta form and the Product card drive, fed
 * the set the work order's OWN quote lines' product categories resolve to
 * (D-1), sectioned when those categories carry a configured layout (spec
 * 0062), flat otherwise.
 *
 * Placement (D-3) and trigger mirror the Offerta model: BELOW the offer
 * lines block and above `WorkOrderTeamSection`, triggered by picking a quote
 * line. Until a line carries one there is no category, hence no attribute,
 * hence nothing to show — that case is decided by the CALLER, which simply
 * does not mount this component (AC-022).
 *
 * The whole block is gated by the single `attribute_values` field permission
 * (AC-024): `MetaField` wraps the renderer's output rather than each leaf
 * field. A configured layout's sections already carry their own card, so
 * that branch mounts the gate directly — nesting it inside a `FormSection`
 * would stack two cards on the same surface (ui-design.md §1-bis).
 */
export function WorkOrderDynamicFieldsSection<TFieldValues extends AttributeLayoutFormShape>({
  control,
  attributes,
  layout,
  isLoading,
  className,
}: WorkOrderDynamicFieldsSectionProps<TFieldValues>) {
  const { t } = useTranslation()
  const title = t('workOrders.form.sections.dynamicFields.title')
  // `AttributeLayoutFormShape` guarantees the key on every accepted form, but
  // TS cannot narrow a literal to `Path<TFieldValues>` through the generic —
  // the same cast `QuoteDynamicFieldsSection` already applies.
  const attributeValuesField = 'attribute_values' as Path<TFieldValues>

  const effectiveAttributes = useMemo(
    () => attributes.map((attribute) => toEffectiveAttribute(attribute, 'work_order')),
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
        <p className="text-sm text-muted-foreground">{t('workOrders.form.sections.dynamicFields.empty')}</p>
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
