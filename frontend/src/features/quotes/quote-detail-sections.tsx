import { useTranslation } from 'react-i18next'
import { GeneralNotesCallout } from '@/components/record-form/general-notes-callout'
import { RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordSkeletonSections } from '@/components/detail/record-skeleton-sections'
import { RewardChipsSection } from '@/features/rewards/reward-chips-section'
import { QuoteAttributesBand, QuoteAttributesSection } from '@/features/quotes/quote-attributes-section'
import { QuoteCompanySection, QuoteDocumentSection } from '@/features/quotes/quote-record-company'
import { QuoteIdentitySection, QuoteInternalNotesRow } from '@/features/quotes/quote-record-identity'
import { QuoteContextSection } from '@/features/quotes/quote-record-context'
import { QuoteContactsSection, QuoteTeamSection } from '@/features/quotes/quote-record-people'
import type { ProductLine } from '@/features/product-lines/types'
import type { QuoteDetailEditor } from '@/features/quotes/use-quote-inline-edit'
import type { QuoteDetailWithPermissions } from '@/features/quotes/types'

/** Spans both columns of `RecordSectionsGrid` — same rule `RecordSection`'s own `full` prop applies. */
const FULL_WIDTH_SECTION_CLASS = '@2xl:col-span-2'

/** Stable empty default: a missing key on older payloads reads the same as `[]`. */
const EMPTY_PRODUCT_LINES: ProductLine[] = []

interface QuoteDetailSectionsProps {
  quote: QuoteDetailWithPermissions
  editor: QuoteDetailEditor
}

/**
 * The offer record's `RecordSectionsGrid` body, every user-written field
 * editable in place (spec 0197, the Commesse model): the internal notes
 * callout first and the Opportunita''s inherited general notes below it, then
 * the offer's data (status included), the inherited
 * context, contacts, buoni, team, company and sites, document and payment.
 * The offer and cost rows follow (`QuoteDetailLines`), then the collected
 * Attribute values (`QuoteDetailAttributes`).
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

      <RecordSkeletonSections
        details={
          <QuoteIdentitySection
            values={{
              code: quote.code,
              title: quote.title,
              quoteDate: quote.quote_date,
              status: quote.quote_workflow_status,
              opportunity: { id: quote.opportunity.id, name: quote.opportunity.name },
            }}
            control={control}
            inline={inline}
            status={{ statuses: quote.quote_workflow_statuses, originalStatusId: quote.quote_workflow_status_id }}
          />
        }
        context={
          <QuoteContextSection
            source={quote.source}
            productLines={quote.product_lines ?? EMPTY_PRODUCT_LINES}
            form={form}
            inline={inline}
          />
        }
        contacts={
          <QuoteContactsSection
            derived={{ registry: quote.registry ?? null, referent: quote.referent ?? null }}
            commercial={quote.commercial}
            reporter={quote.reporter}
            rewards={quote.rewards ?? []}
            form={form}
            inline={inline}
          />
        }
        // Spec 0059 D-3, Offerta origin: the buoni of THIS offer's Segnalatore,
        // edited in the Segnalatore's own row.
        rewards={
          <RewardChipsSection
            title={t('quotes.detail.rewards')}
            rewards={quote.rewards ?? []}
            emptyLabel={t('common.noRewards')}
          />
        }
        team={
          <QuoteTeamSection
            supervisor={quote.supervisor}
            managers={quote.managers ?? []}
            managerLabels={quote.manager_labels}
            synchronized={quote.managers_synchronized ?? false}
            form={form}
            inline={inline}
          />
        }
        company={
          <QuoteCompanySection
            company={quote.company}
            companySite={quote.company_site}
            operationalSite={
              quote.operational_site ? { id: quote.operational_site.id, name: quote.operational_site.label } : null
            }
            form={form}
            inline={inline}
          />
        }
        document={
          <QuoteDocumentSection layout={quote.layout} paymentMethod={quote.payment_method} form={form} inline={inline} />
        }
      />
    </RecordSectionsGrid>
  )
}

/** The collected Attribute values, each a row of its own: the band after the offer rows (`QuoteAttributesBand`). */
export function QuoteDetailAttributes({ quote, editor }: QuoteDetailSectionsProps) {
  if (quote.applicable_attributes.length === 0) {
    return null
  }

  return (
    <QuoteAttributesBand>
      <QuoteAttributesSection
        attributes={quote.applicable_attributes}
        layout={quote.attribute_view_layout ?? null}
        values={quote.attribute_values}
        inline={editor.inline}
        control={editor.form.control}
      />
    </QuoteAttributesBand>
  )
}
