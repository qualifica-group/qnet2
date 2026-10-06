import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { ClipboardList } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { AttributeLayoutField } from '@/features/attributes/attribute-layout-field'
import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'
import { AttributeLayoutView, type AttributeFieldRenderer } from '@/features/attributes/attribute-layout-view'
import { toEffectiveAttribute } from '@/features/request-management/applicable-attribute-adapter'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { ApplicableAttributeSummary } from '@/features/quotes/types'

/** The form-field path of one Attribute's value: the key of its in-place row. */
function attributeFieldPath(code: string): string {
  return `attribute_values.${code}`
}

/** Applicable Attributes as the shared field leaf reads them (context `quote`). */
function useEffectiveAttributes(attributes: ApplicableAttributeSummary[]) {
  return useMemo(() => attributes.map((attribute) => toEffectiveAttribute(attribute, 'quote')), [attributes])
}

interface QuoteAttributesSectionProps {
  /** The set the offer lines' product categories resolve to (spec 0084 D-5). */
  attributes: ApplicableAttributeSummary[]
  /** The detail's view layout (FormMode::View) or, on create, the form one; `null` -> flat. */
  layout: LayoutBlob | null
  /** The values the closed rows show: persisted on the detail, the draft's on create. */
  values: Record<string, unknown>
  inline: InlineEdit
  control: Control<QuoteFormValues>
  className?: string
}

/**
 * "Informazioni aggiuntive" of the quote record, every Attribute a row that
 * edits in place (spec 0197, user directive 2026-10-06 "soprattutto per i
 * campi flessibili"): the configured sections, columns and widths of
 * `AttributeLayoutView`, each value opening on the SAME field leaf the form
 * always rendered (`AttributeLayoutField`). On the detail a confirm PATCHes
 * the map, whose server merge is sparse; on a create draft it only keeps the
 * value.
 *
 * The whole block follows the single `attribute_values` field permission.
 * Absent when the offer lines resolve no Attribute.
 */
export function QuoteAttributesSection({
  attributes,
  layout,
  values,
  inline,
  control,
  className,
}: QuoteAttributesSectionProps) {
  const { t } = useTranslation()
  const effectiveAttributes = useEffectiveAttributes(attributes)

  if (attributes.length === 0) {
    return null
  }

  const effectiveByCode = new Map(effectiveAttributes.map((attribute) => [attribute.code, attribute]))

  const renderField: AttributeFieldRenderer = (attribute, value, fieldClassName) => {
    const effective = effectiveByCode.get(attribute.code)
    return (
      <RecordInlineField
        field={attributeFieldPath(attribute.code)}
        metaKey="attribute_values"
        label={attribute.name}
        inline={inline}
        className={fieldClassName}
        editor={
          effective ? (
            <AttributeLayoutField control={control} attribute={effective} disabled={false} readOnly={false} hideLabel />
          ) : null
        }
      >
        {value}
      </RecordInlineField>
    )
  }

  return (
    <RecordSection title={t('quotes.detail.additionalInformation')} icon={<ClipboardList />} className={className}>
      <AttributeLayoutView layout={layout} attributes={attributes} values={values} renderField={renderField} />
    </RecordSection>
  )
}

interface QuoteNewAttributesFieldsProps {
  /** The set the offer lines being edited resolve to, live. */
  attributes: ApplicableAttributeSummary[]
  /** The codes the persisted lines already cover: those keep their own rows. */
  persistedCodes: ReadonlySet<string>
  control: Control<QuoteFormValues>
}

/**
 * The Attributes a change of products brings in, filled in the offer lines'
 * own editor: they have no row on the detail until the lines are saved, and a
 * required one would otherwise refuse that very save.
 */
export function QuoteNewAttributesFields({ attributes, persistedCodes, control }: QuoteNewAttributesFieldsProps) {
  const { t } = useTranslation()
  const effectiveAttributes = useEffectiveAttributes(attributes)
  const added = effectiveAttributes.filter((attribute) => !persistedCodes.has(attribute.code))

  if (added.length === 0) {
    return null
  }

  return (
    <div className="flex flex-col gap-3 rounded-md border border-border/60 bg-card p-3">
      <p className="text-xs font-semibold text-muted-foreground">{t('quotes.detail.newAttributes')}</p>
      {added.map((attribute) => (
        <AttributeLayoutField key={attribute.code} control={control} attribute={attribute} disabled={false} readOnly={false} />
      ))}
    </div>
  )
}
