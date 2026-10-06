import { useTranslation } from 'react-i18next'
import { Rows3 } from 'lucide-react'
import { RecordField, RecordFieldList } from '@/components/detail/record-panel'
import { cn } from '@/lib/utils'
import { resolveLayoutSections } from '@/features/attributes/attribute-layout-sections'
import type {
  LayoutBlob,
  LayoutItemWidth,
  LayoutSection,
  LayoutSectionVariant,
} from '@/features/attributes/attribute-layout-types'
import { AttributeValueDisplay, type DisplayableAttribute } from '@/features/attributes/attribute-value-display'

interface AttributeLayoutViewProps {
  /** The record's resolved layout (spec 0062); `null`/empty -> one flat field list. */
  layout: LayoutBlob | null
  attributes: DisplayableAttribute[]
  values: Record<string, unknown>
}

interface PlacedAttribute {
  attribute: DisplayableAttribute
  width: LayoutItemWidth
}

/** The heading band's tint per configured variant: only `highlighted` stands out, the rest share the neutral band. */
const HEADING_BAND_CLASS: Record<LayoutSectionVariant, string> = {
  default: 'bg-muted/50',
  informative: 'bg-muted/50',
  secondary: 'bg-muted/50',
  highlighted: 'bg-primary/10',
}

/**
 * A wide section (configured on 2+ columns) takes the full record width and
 * spreads its rows over two columns; hairlines move from `divide-y` to each
 * row's own bottom border, since a two-column grid has no single row order.
 */
const WIDE_SECTION_CLASS = '@2xl:col-span-2'
const WIDE_FIELD_LIST_CLASS =
  '@2xl:grid @2xl:grid-cols-2 @2xl:gap-x-6 @2xl:divide-y-0 @2xl:[&>*]:border-b @2xl:[&>*]:border-border/60'
const WIDE_FULL_FIELD_CLASS = '@2xl:col-span-2'

/** The section's attributes in row order, with their configured width; codes that no longer resolve are skipped. */
function placedAttributes(
  section: LayoutSection,
  attributesByCode: ReadonlyMap<string, DisplayableAttribute>,
): PlacedAttribute[] {
  return section.rows.flatMap((row) =>
    row.items.flatMap((item) => {
      const attribute = attributesByCode.get(item.attribute_code)
      return attribute ? [{ attribute, width: item.width }] : []
    }),
  )
}

interface LayoutViewSectionProps {
  section: LayoutSection
  items: PlacedAttribute[]
  values: Record<string, unknown>
}

/**
 * One layout section in the detail's own idiom (the legacy QNet "info"
 * look): a tinted heading band with icon, title and description, then the
 * same label/value rows as the record sections above it.
 */
function LayoutViewSection({ section, items, values }: LayoutViewSectionProps) {
  const wide = section.columns >= 2

  return (
    <section className={cn('flex min-w-0 flex-col gap-1', wide && WIDE_SECTION_CLASS)}>
      <div className={cn('flex items-start gap-2 rounded-md px-2.5 py-1.5', HEADING_BAND_CLASS[section.variant])}>
        <Rows3 className="mt-0.5 size-3.5 shrink-0 text-primary" aria-hidden="true" />
        <div className="min-w-0">
          <h4 className="text-xs font-semibold text-foreground">{section.title}</h4>
          {section.description ? <p className="text-xs text-muted-foreground">{section.description}</p> : null}
        </div>
      </div>
      <RecordFieldList className={cn('px-1', wide && WIDE_FIELD_LIST_CLASS)}>
        {items.map(({ attribute, width }) => (
          <RecordField
            key={attribute.code}
            label={attribute.name}
            className={cn(wide && width === 'full' && WIDE_FULL_FIELD_CLASS)}
          >
            <AttributeValueDisplay attribute={attribute} value={values[attribute.code]} />
          </RecordField>
        ))}
      </RecordFieldList>
    </section>
  )
}

/**
 * Read-only "Informazioni aggiuntive" for record details (Offerta, Commessa,
 * Prodotto): the form's configured sections — same resolution
 * (`resolveLayoutSections`), order, titles and trailing "Altre informazioni"
 * — each rendered as a titled block of label/value rows, two blocks side by
 * side on a wide record. Without a layout, the flat field list. A section
 * none of whose attributes was handed in is dropped.
 */
export function AttributeLayoutView({ layout, attributes, values }: AttributeLayoutViewProps) {
  const { t } = useTranslation()

  // Step 1: no configured layout -> flat field list, in `sort_order`
  if (!layout || layout.sections.length === 0) {
    return (
      <RecordFieldList>
        {[...attributes]
          .sort((a, b) => a.sort_order - b.sort_order)
          .map((attribute) => (
            <RecordField key={attribute.code} label={attribute.name}>
              <AttributeValueDisplay attribute={attribute} value={values[attribute.code]} />
            </RecordField>
          ))}
      </RecordFieldList>
    )
  }

  // Step 2: the form's sections with what each places, minus those with nothing to show
  const attributesByCode = new Map(attributes.map((attribute) => [attribute.code, attribute]))
  const otherInformationTitle = t('attributes.layout.otherInformation', { defaultValue: 'Altre informazioni' })
  const sections = resolveLayoutSections(layout, attributes, otherInformationTitle)
    .map((section) => ({ section, items: placedAttributes(section, attributesByCode) }))
    .filter(({ items }) => items.length > 0)

  return (
    <div className="grid grid-cols-1 items-start gap-x-6 gap-y-4 @2xl:grid-cols-2">
      {sections.map(({ section, items }) => (
        <LayoutViewSection key={section.id} section={section} items={items} values={values} />
      ))}
    </div>
  )
}
