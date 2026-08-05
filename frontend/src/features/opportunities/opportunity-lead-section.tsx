import { useTranslation } from 'react-i18next'
import { Link2 } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { OpportunityFromLeadBanner } from '@/features/opportunities/opportunity-from-lead-banner'
import { OpportunityLeadField } from '@/features/opportunities/opportunity-lead-field'
import type { OpportunityLeadSelectionState } from '@/features/opportunities/use-opportunity-lead-selection'
import type { OpportunityFormMode } from '@/features/opportunities/types'

interface OpportunityLeadSectionProps {
  mode: OpportunityFormMode
  /** Live state of the in-form picker (create); ignored in edit, where the link is immutable. */
  leadSelection: OpportunityLeadSelectionState
  onSelect: (leadId: number | null) => void
  className?: string
}

/**
 * The record's origin: the optional Lead this opportunity converts (spec 0040
 * amendment A-1). First card of the form because everything below it can be
 * DERIVED from this one choice — picking a lead prefills the anagrafica and
 * locks the BR-2 fields, so burying it under the long column would mean
 * filling by hand what a single pick fills on its own.
 *
 * Create: the picker plus, once a lead is applied, the origin banner. Edit:
 * the link is immutable (D-2), so it reads as a disabled input — and the card
 * is not rendered at all for an opportunity with no lead.
 */
export function OpportunityLeadSection({
  mode,
  leadSelection,
  onSelect,
  className,
}: OpportunityLeadSectionProps) {
  const { t } = useTranslation()

  if (mode.type === 'edit' && !mode.opportunity.lead) {
    return null
  }

  const leadIsBlocked = leadSelection.existingOpportunityId !== null

  return (
    <FormSection
      icon={Link2}
      title={t('opportunities.form.sections.lead.title')}
      description={t('opportunities.form.sections.lead.description')}
      className={className}
    >
      {mode.type === 'create' ? (
        <>
          <OpportunityLeadField state={leadSelection} onSelect={onSelect} />
          {leadSelection.leadId !== null && !leadIsBlocked ? (
            <OpportunityFromLeadBanner registryName={leadSelection.registry?.name ?? null} />
          ) : null}
        </>
      ) : (
        <div className="grid gap-2">
          <Label>{t('opportunities.form.lead')}</Label>
          <Input value={mode.opportunity.lead?.label ?? ''} disabled readOnly />
        </div>
      )}
    </FormSection>
  )
}
