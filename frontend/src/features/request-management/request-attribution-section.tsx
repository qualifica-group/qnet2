import { useTranslation } from 'react-i18next'
import { Route } from 'lucide-react'
import { useWatch, type UseFormReturn } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { ReporterRewardsField } from '@/components/record-form/reporter-rewards-field'
import { RelationSelectField } from '@/components/form/relation-select-field'
import type { ForSelectItem } from '@/features/for-select/types'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { InterceptedRelationSelectField } from '@/features/request-management/intercepted-relation-select-field'
import { FIELD_GRID_CLASS, FIELD_STACK_CLASS } from '@/components/record-form/layout'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'
import type { RequestRelationRef } from '@/features/request-management/types'
import type { RewardAssignmentRef } from '@/features/rewards/types'

/** i18n root of the reward block's strings, resolved inside `RequestRewardsField`. */
const REWARDS_LABEL_PREFIX = 'requestManagement.workPanel.attribution.rewards'

interface RequestAttributionSectionProps {
  /**
   * The whole form, not just its `control`: the reward control below writes
   * its sibling field through `setValue`.
   */
  form: UseFormReturn<RequestWorkFormValues>
  /** The panel's own id: the subject of a Fonte field-change-request proposal (spec 0078). */
  requestId: number
  /** The panel's hydrated `{id, name}` projections, for the pickers' labels. */
  source: RequestRelationRef | null
  reporter: RequestRelationRef | null
  /** Spec 0056: the operational site's `{id,label}` ref, converted to `{id,name}` by the caller (`toRelationFieldRef`). */
  operationalSite: RequestRelationRef | null
  /** Spec 0059 D-3: the panel's persisted reward assignments, for the "abbinamento buono" control under the Segnalatore field. */
  rewards: RewardAssignmentRef[]
  /**
   * The operator half of the Sede <-> Operatore link the FORM owns
   * (`useRequestSiteOperatorLink`, spec 0097 rev-2 D-7): the slot it scopes
   * lives in the "Team" section since rev-2, so this one only reports the Sede
   * it just took and shows the one hydrated from a picked operator.
   */
  autoFilledSite: ForSelectItem | null
  onSiteItemChange: (item: ForSelectItem | null) => void
}

/**
 * Where the request comes from and who reports it (user directive
 * 2026-07-22): "Fonte", "Segnalatore" and the Sede operativa. Every control is
 * metadata-driven, so its per-field gating comes from the server-derived
 * `permissions` block like every other field here.
 *
 * The team left this section with spec 0097 rev-2 D-7
 * (`request-team-section.tsx`). The Sede stayed: it is attribution, not team —
 * but it is also what SCOPES the operator slot, and the two now sit in two
 * different sections. The link is therefore cabled by whoever owns the form
 * (`useRequestSiteOperatorLink`), never here: kept inside one section, the
 * other would stop reacting to it.
 *
 * The reward control (spec 0059 D-3) hangs under the Segnalatore because that
 * reporter is always its beneficiary, and mounts only once there IS one — see
 * `RequestRewardsField`, which owns that rule for both this panel and the
 * create form.
 */
export function RequestAttributionSection({
  form,
  requestId,
  source,
  reporter,
  operationalSite,
  rewards,
  autoFilledSite,
  onSiteItemChange,
}: RequestAttributionSectionProps) {
  const { t } = useTranslation()
  const control = form.control
  const reporterId = useWatch({ control, name: 'reporter_id' })
  const rewardsValue = useWatch({ control, name: 'rewards' })

  const selectLabels = {
    placeholder: t('requestManagement.workPanel.attribution.selectPlaceholder', {
      defaultValue: 'Select',
    }),
    emptyLabel: t('requestManagement.workPanel.attribution.selectEmpty', {
      defaultValue: 'No results',
    }),
    errorLabel: t('requestManagement.workPanel.attribution.selectError', {
      defaultValue: 'Could not load the options.',
    }),
    clearLabel: t('common.clear'),
    retryLabel: t('common.retry'),
  }

  return (
    <FormSection
      icon={Route}
      title={t('requestManagement.workPanel.attribution.title', { defaultValue: 'Attribution' })}
      description={t('requestManagement.workPanel.attribution.description', {
        defaultValue: 'Where the request comes from and who reports it.',
      })}
    >
      <div className={FIELD_GRID_CLASS}>
        <InterceptedRelationSelectField
          control={control}
          name="source_id"
          metaKey="source_id"
          label={t('requestManagement.workPanel.attribution.source', { defaultValue: 'Source' })}
          resource={SOURCES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('requestManagement.workPanel.attribution.sourceSearch', {
            defaultValue: 'Search a source',
          })}
          selected={source}
          changeRequestSubjectId={requestId}
          changeRequestResource={REQUEST_MANAGEMENT_DOMAIN}
          changeRequestField="source_id"
          changeRequestFieldLabelKey="requestManagement.columns.source"
          {...selectLabels}
        />

        <div className={FIELD_STACK_CLASS}>
          <RelationSelectField
            control={control}
            name="reporter_id"
            metaKey="reporter_id"
            label={t('requestManagement.workPanel.attribution.reporter', { defaultValue: 'Reporter' })}
            resource={REFERENTS_FOR_SELECT_RESOURCE}
            searchPlaceholder={t('requestManagement.workPanel.attribution.reporterSearch', {
              defaultValue: 'Search a reporter',
            })}
            selected={reporter}
            {...selectLabels}
          />
          <ReporterRewardsField
            labelPrefix={REWARDS_LABEL_PREFIX}
            reporterId={reporterId}
            value={rewardsValue}
            onChange={(next) => form.setValue('rewards', next, { shouldDirty: true })}
            initialAssignments={rewards}
          />
        </div>

        <RelationSelectField
          control={control}
          name="operational_site_id"
          metaKey="operational_site_id"
          label={t('requestManagement.workPanel.attribution.operationalSite', { defaultValue: 'Operational site' })}
          resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('requestManagement.workPanel.attribution.operationalSiteSearch', {
            defaultValue: 'Search a site',
          })}
          selected={autoFilledSite ? { id: autoFilledSite.id, name: autoFilledSite.label } : operationalSite}
          onItemChange={onSiteItemChange}
          {...selectLabels}
        />
      </div>
    </FormSection>
  )
}
