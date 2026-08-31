import { useTranslation } from 'react-i18next'
import { Label } from '@/components/ui/label'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { ExistingOpportunityAlert } from '@/features/opportunities/existing-opportunity-alert'
import { LEADS_FOR_SELECT_RESOURCE } from '@/features/leads/for-select-api'
import type { OpportunityLeadSelectionState } from '@/features/opportunities/use-opportunity-lead-selection'

interface OpportunityLeadFieldProps {
  state: OpportunityLeadSelectionState
  onSelect: (leadId: number | null) => void
}

/**
 * The CREATE form's optional "Lead" picker (spec 0040 amendment A-1,
 * AC-086/087): a standalone `AsyncPaginatedSelect` — not a `MetaField`, since
 * `lead_id` is never an RHF field of the opportunity form, only a submit-time
 * addition. All the actual BR-1/BR-2 logic (apply/clear/existing-opportunity
 * block) lives in `useOpportunityLeadSelection`; this component only renders
 * its state.
 */
export function OpportunityLeadField({ state, onSelect }: OpportunityLeadFieldProps) {
  const { t } = useTranslation()

  const selectedItem =
    state.leadId !== null ? { id: state.leadId, label: state.registry?.name ?? `#${state.leadId}` } : null

  return (
    <div className="flex flex-col gap-2">
      <div className="grid gap-2">
        <Label>{t('opportunities.form.lead')}</Label>
        <AsyncPaginatedSelect
          resource={LEADS_FOR_SELECT_RESOURCE}
          value={state.leadId}
          onChange={onSelect}
          selectedItem={selectedItem}
          disabled={state.isApplying}
          labels={{
            placeholder: t('opportunities.form.selectPlaceholder'),
            searchPlaceholder: t('opportunities.form.leadSearch'),
            empty: t('opportunities.form.selectEmpty'),
            error: t('opportunities.form.selectError'),
            clearLabel: t('common.clear'),
            triggerLabel: t('opportunities.form.lead'),
            retry: t('common.retry'),
          }}
        />
      </div>

      {state.isError ? (
        <p role="alert" className="text-sm text-destructive">
          {t('opportunities.form.leadLoadError')}
        </p>
      ) : null}

      {state.existingOpportunityId !== null ? (
        <ExistingOpportunityAlert
          opportunityId={state.existingOpportunityId}
          message={t('opportunities.form.existingOpportunityTitle')}
        />
      ) : null}
    </div>
  )
}
