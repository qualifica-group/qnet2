import { useTranslation } from 'react-i18next'
import { Handshake } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { GeneralNotesCallout } from '@/components/record-form/general-notes-callout'
import { RecordField, RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { ProductLinesReadOnlyList } from '@/features/product-lines/product-lines-read-only-list'
import { RewardChipsSection } from '@/features/rewards/reward-chips-section'
import { QuoteAttributesSection } from '@/features/quotes/quote-attributes-section'
import { QuoteCompanySection, QuoteDocumentSection } from '@/features/quotes/quote-record-company'
import { QuoteIdentitySection, QuoteInternalNotesRow } from '@/features/quotes/quote-record-identity'
import { QuoteContactsSection, QuoteTeamSection } from '@/features/quotes/quote-record-people'
import type { ProductLine } from '@/features/product-lines/types'
import type { QuoteDetailEditor } from '@/features/quotes/use-quote-inline-edit'
import type { QuoteDetailWithPermissions } from '@/features/quotes/types'

/** Spans both columns of `RecordSectionsGrid` — same rule `RecordSection`'s own `full` prop applies. */
const FULL_WIDTH_SECTION_CLASS = '@2xl:col-span-2'

/** Stable empty defaults: a missing key on older fixtures reads the same as `[]`. */
const EMPTY_PRODUCT_LINES: ProductLine[] = []

/**
 * Contesto: what the Offerta INHERITS from its Opportunita' and does not own
 * (user request 2026-08-31) — source and product lines, a read-only projection
 * of the parent record. The inherited general notes are not a row here: they
 * wear the notes callout, next to the offer's own internal notes.
 */
function QuoteContextSection({ quote }: { quote: QuoteDetailWithPermissions }) {
  const { t } = useTranslation()

  return (
    <RecordSection title={t('quotes.detail.sections.context')} icon={<Handshake />}>
      <RecordFieldList>
        <RecordField label={t('quotes.detail.source')}>
          {quote.source ? quote.source.name : <DetailEmpty />}
        </RecordField>
        <RecordField label={t('quotes.detail.productLines')}>
          <ProductLinesReadOnlyList lines={quote.product_lines ?? EMPTY_PRODUCT_LINES} />
        </RecordField>
      </RecordFieldList>
    </RecordSection>
  )
}

interface QuoteDetailSectionsProps {
  quote: QuoteDetailWithPermissions
  editor: QuoteDetailEditor
}

/**
 * The offer record's `RecordSectionsGrid` body, every user-written field
 * editable in place (spec 0197, the Commesse model): the internal notes
 * callout first and the Opportunita''s inherited general notes below it, then
 * the offer's data (status included), the inherited
 * context, contacts, buoni, team, company and sites, document and payment,
 * and the collected Attribute values — each Attribute a row of its own. The
 * offer and cost rows are the record card's closing band (`QuoteDetailLines`).
 */
export function QuoteDetailSections({ quote, editor }: QuoteDetailSectionsProps) {
  const { t } = useTranslation()
  const { form, inline } = editor
  const { control } = form

  return (
    <RecordSectionsGrid>
      <QuoteInternalNotesRow
        notes={quote.internal_notes}
        control={control}
        inline={inline}
        className={FULL_WIDTH_SECTION_CLASS}
      />

      {/* Inherited from the Opportunita', read-only: hidden when it carries none. */}
      <GeneralNotesCallout
        title={t('quotes.detail.opportunityGeneralNotes')}
        notes={quote.general_notes}
        className={FULL_WIDTH_SECTION_CLASS}
      />

      <QuoteIdentitySection
        values={{
          code: quote.code,
          title: quote.title,
          status: quote.quote_workflow_status,
          opportunity: { id: quote.opportunity.id, name: quote.opportunity.name },
        }}
        control={control}
        inline={inline}
        status={{ statuses: quote.quote_workflow_statuses, originalStatusId: quote.quote_workflow_status_id }}
      />

      <QuoteContextSection quote={quote} />

      <QuoteContactsSection
        derived={{ registry: quote.registry ?? null, referent: quote.referent ?? null }}
        commercial={quote.commercial}
        reporter={quote.reporter}
        rewards={quote.rewards ?? []}
        form={form}
        inline={inline}
      />

      {/* Spec 0059 D-3, Offerta origin: the buoni of THIS offer's Segnalatore,
          edited in the Segnalatore's own row. */}
      <RewardChipsSection title={t('quotes.detail.rewards')} rewards={quote.rewards ?? []} />

      <QuoteTeamSection
        supervisor={quote.supervisor}
        managers={quote.managers ?? []}
        managerLabels={quote.manager_labels}
        synchronized={quote.managers_synchronized ?? false}
        form={form}
        inline={inline}
      />

      <QuoteCompanySection
        company={quote.company}
        companySite={quote.company_site}
        operationalSite={
          quote.operational_site ? { id: quote.operational_site.id, name: quote.operational_site.label } : null
        }
        form={form}
        inline={inline}
      />

      <QuoteDocumentSection layout={quote.layout} paymentMethod={quote.payment_method} form={form} inline={inline} />

      <QuoteAttributesSection
        attributes={quote.applicable_attributes}
        layout={quote.attribute_view_layout ?? null}
        values={quote.attribute_values}
        inline={inline}
        control={control}
        className={FULL_WIDTH_SECTION_CLASS}
      />
    </RecordSectionsGrid>
  )
}
