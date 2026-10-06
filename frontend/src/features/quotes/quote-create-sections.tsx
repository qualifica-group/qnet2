import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Loader2, SlidersHorizontal } from 'lucide-react'
import { RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { QuoteAttributesSection } from '@/features/quotes/quote-attributes-section'
import { QuoteCompanySection, QuoteDocumentSection } from '@/features/quotes/quote-record-company'
import { QuoteIdentitySection, QuoteInternalNotesRow } from '@/features/quotes/quote-record-identity'
import { QuoteContactsSection, QuoteTeamSection } from '@/features/quotes/quote-record-people'
import { useQuoteDraftLabels } from '@/features/quotes/use-quote-draft-labels'
import { useQuoteManagerLabels } from '@/features/quotes/use-quote-manager-labels'
import type { useQuoteCreateDefaults } from '@/features/quotes/use-quote-create-defaults'
import type { QuoteFormState } from '@/features/quotes/use-quote-form'
import type { RewardAssignmentRef } from '@/features/rewards/types'

/** Spans both columns of `RecordSectionsGrid` — same rule `RecordSection`'s own `full` prop applies. */
const FULL_WIDTH_SECTION_CLASS = '@2xl:col-span-2'

/** A new Offerta starts with no buono (directive 2026-08-31): nothing persisted seeds the chips. */
const NO_REWARDS: RewardAssignmentRef[] = []

interface QuoteCreateSectionsProps {
  quoteForm: QuoteFormState
  draft: InlineEdit
  defaults: ReturnType<typeof useQuoteCreateDefaults>
}

/**
 * The create form as a replica of the quote detail (spec 0197 D-6, the 0195
 * D-8 rule): the detail's sections, rows, labels and order, every row CLOSED
 * until clicked — empty or prefilled — and opening on the same field
 * component the detail edits in place. Nothing is saved per row: the
 * header's Salva validates and creates the whole draft. What only exists once
 * created (status, the Opportunita's projected context, buoni chips) is left
 * out; the Opportunita' itself, fixed after creation, is picked here and
 * prefills roles, sede and G.A.
 */
export function QuoteCreateSections({ quoteForm, draft, defaults }: QuoteCreateSectionsProps) {
  const { t } = useTranslation()
  const { form, attributeContext, attributesLoading, hasPickedProduct } = quoteForm
  const refs = useQuoteDraftLabels(form.control, {
    inheritedMeta: defaults.inheritedMeta,
    opportunity: defaults.forcedOpportunity,
    layout: defaults.defaultLayout,
  })
  const { values } = refs
  // Spec 0087 D-8: the slots take their names from the rows being typed.
  const offerLines = useWatch({ control: form.control, name: 'offer_lines' })
  const managerLabels = useQuoteManagerLabels(offerLines, values.opportunity_id ?? null)

  return (
    <RecordSectionsGrid>
      <QuoteInternalNotesRow
        notes={values.internal_notes ?? null}
        control={form.control}
        inline={draft}
        className={FULL_WIDTH_SECTION_CLASS}
      />

      <QuoteIdentitySection
        values={{ code: values.code ?? '', title: values.title ?? '', status: null, opportunity: refs.opportunity }}
        control={form.control}
        inline={draft}
        opportunity={{
          onItemChange: defaults.handleOpportunityItemChange,
          forceDisabled: defaults.isOpportunityForced,
          registryId: defaults.opportunityRegistryId,
        }}
      />

      <QuoteContactsSection
        commercial={refs.commercial}
        reporter={refs.reporter}
        rewards={NO_REWARDS}
        rewardCount={values.rewards?.length ?? 0}
        form={form}
        inline={draft}
      />

      <QuoteTeamSection
        supervisor={refs.supervisor}
        managers={refs.managers}
        managerLabels={managerLabels}
        synchronized={false}
        form={form}
        inline={draft}
      />

      <QuoteCompanySection
        company={refs.company}
        companySite={refs.companySite}
        operationalSite={refs.operationalSite}
        form={form}
        inline={draft}
      />

      <QuoteDocumentSection layout={refs.layout} paymentMethod={refs.paymentMethod} form={form} inline={draft} />

      {/* Spec 0084 D-5: nothing to show until a line carries a product. */}
      {hasPickedProduct && attributesLoading ? (
        <RecordSection
          title={t('quotes.detail.additionalInformation')}
          icon={<SlidersHorizontal />}
          className={FULL_WIDTH_SECTION_CLASS}
        >
          <p className="flex items-center gap-2 text-sm text-muted-foreground">
            <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
            {t('common.loading')}
          </p>
        </RecordSection>
      ) : (
        <QuoteAttributesSection
          attributes={attributeContext.applicable_attributes}
          layout={attributeContext.attribute_layout}
          values={values.attribute_values ?? {}}
          inline={draft}
          control={form.control}
          className={FULL_WIDTH_SECTION_CLASS}
        />
      )}
    </RecordSectionsGrid>
  )
}
