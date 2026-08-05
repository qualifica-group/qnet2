import { useTranslation } from 'react-i18next'
import { Building2 } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { STATES_FOR_SELECT_RESOURCE } from '@/features/geo/state-for-select-api'
import { OpportunityWorkflowStatusField } from '@/features/opportunities/opportunity-workflow-status-field'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type { OpportunitySelectedItems } from '@/features/opportunities/use-opportunity-selected-items'
import { Label } from '@/components/ui/label'
import { OpportunityStatusBadge } from '@/features/opportunities/opportunity-status-badge'
import type { OpportunityStatusSummary, OpportunityWorkflowStatusRef } from '@/features/opportunities/types'

interface OpportunityClassificationSectionProps {
  control: Control<OpportunityFormValues>
  selectedItems: OpportunitySelectedItems
  /** BR-2: keys derived from a linked Lead, forced read-only (spec 0040 MT-6; empty outside that flow). */
  lockedFields: ReadonlySet<string>
  /** Spec 0047 (AC-026): the resolved working-state set, or `null` in create mode (not yet known). */
  workflowStatuses: OpportunityWorkflowStatusRef[] | null
  /** Spec 0082: the COMPUTED status, shown read-only. `null` in create mode (no quote exists yet). */
  status: OpportunityStatusSummary | null
  className?: string
}

/**
 * The opportunity's classification block: the read-only computed status
 * (spec 0082) plus the source/site/state relations. Split out of
 * `OpportunityFormBody` to stay within the engineering size limits (mirrors
 * `CampaignPlanningSection`).
 */
export function OpportunityClassificationSection({
  control,
  selectedItems,
  lockedFields,
  workflowStatuses,
  status,
  className,
}: OpportunityClassificationSectionProps) {
  const { t } = useTranslation()

  const selectLabels = {
    placeholder: t('opportunities.form.selectPlaceholder'),
    emptyLabel: t('opportunities.form.selectEmpty'),
    errorLabel: t('opportunities.form.selectError'),
    clearLabel: t('common.clear'),
    retryLabel: t('common.retry'),
  }

  return (
    <FormSection
      icon={Building2}
      title={t('opportunities.form.sections.classification.title')}
      description={t('opportunities.form.sections.classification.description')}
      className={className}
    >
      <div className="grid gap-3 sm:grid-cols-2">
        {/*
          Spec 0082: the status is COMPUTED from the opportunity's quotes and is
          never submitted — a read-only badge, not a field. In create mode there
          is no quote yet, so the placeholder stands in for it.
        */}
        <div className="space-y-2">
          <Label>{t('opportunities.form.opportunityStatus')}</Label>
          <div className="flex min-h-9 items-center">
            {status && status.entries.length > 0 ? (
              <OpportunityStatusBadge summary={status} />
            ) : (
              <span className="text-sm text-muted-foreground">{t('opportunities.status.empty')}</span>
            )}
          </div>
        </div>

        <RelationSelectField
          control={control}
          name="source_id"
          metaKey="source_id"
          label={t('opportunities.form.source')}
          resource={SOURCES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('opportunities.form.sourceSearch')}
          selected={selectedItems.source}
          forceDisabled={lockedFields.has('source_id')}
          {...selectLabels}
        />

        <RelationSelectField
          control={control}
          name="operational_site_id"
          metaKey="operational_site_id"
          label={t('opportunities.form.operationalSite')}
          resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('opportunities.form.operationalSiteSearch')}
          selected={selectedItems.operationalSite}
          {...selectLabels}
        />

        <RelationSelectField
          control={control}
          name="state_id"
          metaKey="state_id"
          label={t('opportunities.form.state')}
          resource={STATES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('opportunities.form.stateSearch')}
          selected={selectedItems.state}
          {...selectLabels}
        />

        <OpportunityWorkflowStatusField control={control} statuses={workflowStatuses} />
      </div>
    </FormSection>
  )
}
