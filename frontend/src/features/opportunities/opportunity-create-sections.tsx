import { useTranslation } from 'react-i18next'
import { Link2 } from 'lucide-react'
import type { UseFormReturn } from 'react-hook-form'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { RewardChipsSection } from '@/features/rewards/reward-chips-section'
import { OpportunityFromLeadBanner } from '@/features/opportunities/opportunity-from-lead-banner'
import { OpportunityLeadField } from '@/features/opportunities/opportunity-lead-field'
import { OpportunityClientRecordSection } from '@/features/opportunities/opportunity-record-client'
import {
  OpportunityClassificationRecordSection,
  OpportunityTeamRecordSection,
} from '@/features/opportunities/opportunity-record-classification'
import { OpportunityDetailsSection, OpportunityGeneralNotesRow } from '@/features/opportunities/opportunity-record-details'
import { useOpportunityDraftValues } from '@/features/opportunities/use-opportunity-draft-values'
import type { OpportunityLeadSelectionState } from '@/features/opportunities/use-opportunity-lead-selection'
import type { BlockingOpportunity, OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

/** Spans both columns of `RecordSectionsGrid` — same rule `RecordSection`'s own `full` prop applies. */
const FULL_WIDTH_SECTION_CLASS = '@2xl:col-span-2'

/** The draft's classification rows, named as the detail's read-only list names them. */
function DraftProductLines({ labels }: { labels: string[] }) {
  if (labels.length === 0) {
    return <DetailEmpty />
  }
  return (
    <ul className="flex flex-col gap-1">
      {labels.map((label, index) => (
        // Two rows may name the same category while being edited: the index keeps them apart.
        <li key={`${index}-${label}`} className="min-w-0 truncate font-medium">
          {label}
        </li>
      ))}
    </ul>
  )
}

interface OpportunityCreateSectionsProps {
  form: UseFormReturn<OpportunityFormValues>
  draft: InlineEdit
  leadSelection: OpportunityLeadSelectionState
  onSelectLead: (leadId: number | null) => void
  /** The open opportunity the chosen anagrafica already has, when the server refused the create over it. */
  blockingOpportunity: BlockingOpportunity | null
}

/**
 * The create form as a replica of the opportunity detail (spec 0198): the
 * detail's sections, rows, labels and order, every row CLOSED until clicked —
 * empty or prefilled — and opening on the same field component the detail
 * edits in place. Nothing is saved per row: the header's Salva validates and
 * creates the whole draft.
 *
 * The originating Lead comes first and stays open: it is not a form field,
 * and picking it fills (and locks, BR-2) the derived rows below — the closed
 * rows show what it handed down, the locked ones without a pencil.
 */
export function OpportunityCreateSections({
  form,
  draft,
  leadSelection,
  onSelectLead,
  blockingOpportunity,
}: OpportunityCreateSectionsProps) {
  const { t } = useTranslation()
  const { values, productLineLabels } = useOpportunityDraftValues(form.control, leadSelection.registry)
  const leadIsApplied = leadSelection.leadId !== null && leadSelection.existingOpportunityId === null
  const sectionProps = { values, form, inline: draft, lockedFields: new Set(leadSelection.lockedFields) }

  return (
    <RecordSectionsGrid>
      <RecordSection
        title={t('opportunities.form.sections.lead.title')}
        icon={<Link2 />}
        className={FULL_WIDTH_SECTION_CLASS}
      >
        <div className="flex flex-col gap-2">
          <OpportunityLeadField state={leadSelection} onSelect={onSelectLead} />
          {leadIsApplied ? <OpportunityFromLeadBanner registryName={leadSelection.registry?.name ?? null} /> : null}
        </div>
      </RecordSection>

      <OpportunityGeneralNotesRow
        notes={values.general_notes}
        form={form}
        inline={draft}
        className={FULL_WIDTH_SECTION_CLASS}
      />

      <OpportunityDetailsSection {...sectionProps} />

      <OpportunityClientRecordSection {...sectionProps} blockingOpportunity={blockingOpportunity} />

      <OpportunityClassificationRecordSection
        {...sectionProps}
        productLines={<DraftProductLines labels={productLineLabels} />}
      />

      {/* Spec 0087 (D-7): nothing to sync yet on a still-unsaved create. */}
      <OpportunityTeamRecordSection {...sectionProps} managersSynchronized={false} />

      <RewardChipsSection title={t('opportunities.detail.rewards')} rewards={values.rewards} />
    </RecordSectionsGrid>
  )
}
