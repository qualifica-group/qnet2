import { useTranslation } from 'react-i18next'
import { RecordSectionsGrid } from '@/components/detail/record-panel'
import { ProductLinesReadOnlyList } from '@/features/product-lines/product-lines-read-only-list'
import { RewardChipsSection } from '@/features/rewards/reward-chips-section'
import { OpportunityClientRecordSection } from '@/features/opportunities/opportunity-record-client'
import {
  OpportunityClassificationRecordSection,
  OpportunityTeamRecordSection,
} from '@/features/opportunities/opportunity-record-classification'
import { OpportunityDetailsSection, OpportunityGeneralNotesRow } from '@/features/opportunities/opportunity-record-details'
import type { OpportunityRecordValues } from '@/features/opportunities/opportunity-record'
import type { OpportunityDetailEditor } from '@/features/opportunities/use-opportunity-inline-edit'
import type { OpportunityDetailWithPermissions } from '@/features/opportunities/types'

/** Spans both columns of `RecordSectionsGrid` — same rule `RecordSection`'s own `full` prop applies. */
const FULL_WIDTH_SECTION_CLASS = '@2xl:col-span-2'

/** The persisted opportunity as the record's rows read it. */
function persistedValues(opportunity: OpportunityDetailWithPermissions): OpportunityRecordValues {
  return {
    name: opportunity.name,
    start_date: opportunity.start_date,
    expected_close_date: opportunity.expected_close_date,
    estimated_value: opportunity.estimated_value,
    success_probability: opportunity.success_probability,
    general_notes: opportunity.general_notes ?? null,
    registry: opportunity.registry,
    referent: opportunity.referent,
    commercial: opportunity.commercial,
    reporter: opportunity.reporter,
    lead: opportunity.lead,
    source: opportunity.source,
    supervisor: opportunity.supervisor,
    managers: opportunity.managers,
    manager_labels: opportunity.manager_labels,
    products_of_interest: opportunity.products_of_interest ?? [],
    rewards: opportunity.rewards ?? [],
  }
}

interface OpportunityDetailSectionsProps {
  opportunity: OpportunityDetailWithPermissions
  editor: OpportunityDetailEditor
}

/**
 * The opportunity record's `RecordSectionsGrid` body, every user-written
 * field editable in place (spec 0198): the general notes callout first, then
 * details, client and contacts, classification, team and the assigned
 * rewards (read-only, the block shared with the Offerta; they are edited
 * with the Segnalatore they belong to). The status is computed from the
 * quotes (spec 0082): it stays the header pill.
 */
export function OpportunityDetailSections({ opportunity, editor }: OpportunityDetailSectionsProps) {
  const { t } = useTranslation()
  const { form, inline, blockingOpportunity } = editor
  const values = persistedValues(opportunity)
  const sectionProps = { values, form, inline, lockedFields: new Set(opportunity.locked_fields) }

  return (
    <RecordSectionsGrid>
      <OpportunityGeneralNotesRow
        notes={values.general_notes}
        form={form}
        inline={inline}
        className={FULL_WIDTH_SECTION_CLASS}
      />

      <OpportunityDetailsSection {...sectionProps} />

      <OpportunityClientRecordSection {...sectionProps} blockingOpportunity={blockingOpportunity} />

      <OpportunityClassificationRecordSection
        {...sectionProps}
        productLines={<ProductLinesReadOnlyList lines={opportunity.product_lines} />}
      />

      <OpportunityTeamRecordSection
        {...sectionProps}
        managersSynchronized={opportunity.managers_synchronized ?? false}
      />

      <RewardChipsSection title={t('opportunities.detail.rewards')} rewards={values.rewards} />
    </RecordSectionsGrid>
  )
}
