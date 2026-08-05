import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Info, Route } from 'lucide-react'
import { useWatch, type UseFormReturn } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { ReporterRewardsField } from '@/components/record-form/reporter-rewards-field'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { useResourcePermissions } from '@/features/authorization/permissions'
import type { ForSelectItem } from '@/features/for-select/types'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { USERS_FOR_SELECT_RESOURCE, type UserForSelectItem } from '@/features/users/for-select-api'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { InterceptedRelationSelectField } from '@/features/request-management/intercepted-relation-select-field'
import { FIELD_GRID_CLASS, FIELD_STACK_CLASS } from '@/components/record-form/layout'
import { OPERATOR_MANAGER_LABEL_POSITION, REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'
import type { ManagerLabels, RequestRelationRef } from '@/features/request-management/types'
import type { RewardAssignmentRef } from '@/features/rewards/types'

/** i18n root of the reward block's strings, resolved inside `RequestRewardsField`. */
const REWARDS_LABEL_PREFIX = 'requestManagement.workPanel.attribution.rewards'

interface RequestAttributionSectionProps {
  /**
   * The whole form, not just its `control`: the Sede <-> Operatore link below
   * writes the other field through `setValue`.
   */
  form: UseFormReturn<RequestWorkFormValues>
  /** The panel's own id: the subject of a Fonte field-change-request proposal (spec 0078). */
  requestId: number
  /** The panel's hydrated `{id, name}` projections, for the pickers' labels. */
  source: RequestRelationRef | null
  reporter: RequestRelationRef | null
  operator: RequestRelationRef | null
  /** Spec 0056: the operational site's `{id,label}` ref, converted to `{id,name}` by the caller (`toRelationFieldRef`). */
  operationalSite: RequestRelationRef | null
  /** Spec 0059 D-3: the panel's persisted reward assignments, for the "abbinamento buono" control under the Segnalatore field. */
  rewards: RewardAssignmentRef[]
  /**
   * Spec 0080: the panel's own resolved G.A. labels (string position keys),
   * as returned by `RequestWorkPanel.manager_labels`. Used to relabel the GA2
   * "Operatore" field when the request's category defines one; `undefined`
   * (or a map missing position 2) keeps today's "Operator (GA2)" string.
   */
  managerLabels?: ManagerLabels
}

/**
 * Where the request comes from and who owns it (user directive 2026-07-22):
 * "Fonte", "Segnalatore" and the GA2 "Operatore" — the same three dimensions
 * the opportunities form carries, made editable from the work panel too. Each
 * picker is the shared `RelationSelectField`, so its per-field gating comes
 * from the server-derived `permissions` block like every other field here.
 *
 * Changing the operator REASSIGNS the request: an actor without
 * `request-management.viewAll` loses access to it on the next read (D-3
 * scope), which is the intended semantics of handing a request over.
 *
 * Sede <-> Operatore are reciprocally linked exactly as in the Lead form
 * (`lead-form-body.tsx`, spec 0048 AC-060..062), user directive 2026-07-23:
 * the operator list is scoped to the chosen Sede, picking an operator first
 * hydrates its own Sede from `meta`, and a real Sede change clears a now
 * out-of-scope operator.
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
  operator,
  operationalSite,
  rewards,
  managerLabels,
}: RequestAttributionSectionProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const control = form.control
  const reporterId = useWatch({ control, name: 'reporter_id' })
  const rewardsValue = useWatch({ control, name: 'rewards' })
  const operatorLabel =
    managerLabels?.[OPERATOR_MANAGER_LABEL_POSITION] ??
    t('requestManagement.workPanel.attribution.operator', { defaultValue: 'Operator' })

  // Baseline every auto-fill/clear below reasons against: it starts at the
  // panel's persisted Sede and only ever moves inside an event handler (never
  // read/written during render), so a REAL Sede pick is told apart from the
  // programmatic auto-fill (which never goes through the Sede field's own
  // `onItemChange`).
  const previousSiteIdRef = useRef<number | null>(operationalSite?.id ?? null)
  const siteId = useWatch({ control, name: 'operational_site_id' })
  const [autoFilledSite, setAutoFilledSite] = useState<RelationFieldRef | null>(null)

  // The scoping hint is a sibling of the picker, so it does NOT disappear with
  // it: `MetaField` hides a non-visible field from inside, leaving the sentence
  // behind to describe a control (and a Sede) the actor cannot see. It belongs
  // on screen only when both fields are actually rendered (user directive
  // 2026-08-04).
  const showOperatorScopeHint =
    siteId != null && fieldPermission('operator_id').visible && fieldPermission('operational_site_id').visible

  // Operatore -> Sede: picking an operator hydrates its own Sede from `meta`
  // (no extra fetch). An operator with no Sede leaves the current value alone.
  const handleOperatorItemChange = (item: ForSelectItem | null) => {
    const site = (item as UserForSelectItem | null)?.meta
    if (site?.operational_site_id == null) return
    form.setValue('operational_site_id', site.operational_site_id, {
      shouldDirty: true,
      shouldValidate: true,
    })
    setAutoFilledSite({
      id: site.operational_site_id,
      name: site.operational_site_label ?? `#${site.operational_site_id}`,
    })
    previousSiteIdRef.current = site.operational_site_id
  }

  // Sede -> Operatore: a real pick/clear re-scopes the operator list, so an
  // operator from another Sede can no longer be assumed valid and is cleared
  // — only on an ACTUAL change, never on the programmatic auto-fill above.
  const handleSiteItemChange = (item: ForSelectItem | null) => {
    const nextSiteId = item?.id ?? null
    if (nextSiteId !== previousSiteIdRef.current) {
      form.setValue('operator_id', null, { shouldDirty: true })
    }
    previousSiteIdRef.current = nextSiteId
  }

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
        defaultValue: 'Where the request comes from and who is working on it.',
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
          selected={autoFilledSite ?? operationalSite}
          onItemChange={handleSiteItemChange}
          {...selectLabels}
        />

        {/* After the Sede on purpose: the Sede is what scopes this list. */}
        <div className={FIELD_STACK_CLASS}>
          <RelationSelectField
            control={control}
            name="operator_id"
            metaKey="operator_id"
            label={operatorLabel}
            resource={USERS_FOR_SELECT_RESOURCE}
            searchPlaceholder={t('requestManagement.workPanel.attribution.operatorSearch', {
              defaultValue: 'Search an operator',
            })}
            selected={operator}
            onItemChange={handleOperatorItemChange}
            params={siteId != null ? { operational_site_id: siteId } : undefined}
            showAvatar
            {...selectLabels}
          />
          {showOperatorScopeHint && (
            <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
              <Info className="size-3.5 shrink-0" aria-hidden="true" />
              {t('requestManagement.workPanel.attribution.operatorFilteredBySite', {
                defaultValue: 'Only the operators of the selected site.',
              })}
            </p>
          )}
        </div>
      </div>
    </FormSection>
  )
}
