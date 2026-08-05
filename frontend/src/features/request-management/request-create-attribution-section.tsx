import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Info, Route } from 'lucide-react'
import { useWatch, type UseFormReturn } from 'react-hook-form'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { FormSection } from '@/components/form-section'
import { ReporterRewardsField } from '@/components/record-form/reporter-rewards-field'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import { useAbilities } from '@/features/auth/use-abilities'
import type { ForSelectItem } from '@/features/for-select/types'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { USERS_FOR_SELECT_RESOURCE, type UserForSelectItem } from '@/features/users/for-select-api'
import { FIELD_GRID_CLASS, FIELD_STACK_CLASS } from '@/components/record-form/layout'
import type { RequestCreateFormValues } from '@/features/request-management/request-create-schema'
import { OPERATOR_MANAGER_LABEL_POSITION } from '@/features/request-management/types'
import type { RewardAssignmentRef } from '@/features/rewards/types'
import { useActiveCategoryManagerLabels } from '@/features/request-management/use-active-category-manager-labels'
import { useRequestManagementCategoryPreference } from '@/features/request-management/use-request-management-category-preference'
import {
  ASSIGN_OPERATOR_PERMISSION,
  OPERATIONAL_SITES_VIEW_ANY_PERMISSION,
} from '@/features/request-management/use-request-actor-defaults'

/** i18n root of the reward block's strings, resolved inside `RequestRewardsField`. */
const REWARDS_LABEL_PREFIX = 'requestManagement.form.create.attribution.rewards'

/** Hoisted: nothing is persisted yet on a create form, and a fresh `[]` per render would reseed the chips' cache. */
const EMPTY_ASSIGNMENTS: RewardAssignmentRef[] = []

interface RequestCreateAttributionSectionProps {
  form: UseFormReturn<RequestCreateFormValues>
  /** Server 422 for the reward/reporter D-3 guard, collected as one banner (see `useRequestCreateForm`). */
  rewardsError: string | null
}

/**
 * The create form's attribution section (user directive 2026-07-24): the same
 * "Fonte"/"Segnalatore"/"buono" trio the work panel's view already carries,
 * available at creation so a request can be attributed up front, plus the GA2
 * "Operatore" (user directive 2026-07-29). Both pickers are the plain
 * `AsyncPaginatedSelect` (not the work panel's meta-driven
 * `RelationSelectField`): this create-only form has no `permissions` envelope
 * to gate fields against — creation is gated wholesale by
 * `request-management.create` server-side — so it mirrors the registry picker
 * of `RequestCreateClientSection` instead. Each relation carries the shared
 * quick-create "+" (spec 0028) so a missing Fonte/Segnalatore/utente can be
 * created without leaving the form.
 *
 * Fonte is REQUIRED (user directive 2026-07-29), mirroring
 * `StoreRequestRequest`'s own `required` rule.
 *
 * Operatore and Sede operativa are the two fields NOT covered by
 * `request-management.create`: attributing a request is supervisory, so each is
 * rendered only for an actor holding its own ability
 * (`request-management.assignOperator` / `operational-sites.viewAny`, user
 * directive 2026-08-03) — the same two the store endpoint enforces server-side.
 *
 * Sede <-> Operatore are reciprocally linked exactly as in the work panel
 * (`request-attribution-section.tsx`) and in the Lead form (spec 0048
 * AC-060..062), user directive 2026-07-31: the operator list is scoped to the
 * chosen Sede, picking an operator first hydrates its own Sede from `meta`,
 * and a real Sede change clears a now out-of-scope operator. Field order
 * matches the work panel's for the same reason — the Sede comes first because
 * it is what scopes the list below it.
 *
 * The reward control (spec 0059 D-3) sits under the Segnalatore because the
 * beneficiary is always that reporter, and is mounted only once there IS one:
 * `RequestRewardsField` owns that rule for this form and the work panel alike.
 *
 * "Operatore" relabeling (spec 0080): the create form has no persisted
 * request yet to resolve its own G.A. labels from (unlike the work panel), so
 * it falls back to the module's currently active category tab
 * (`useRequestManagementCategoryPreference`) and resolves ITS effective
 * labels (`useActiveCategoryManagerLabels`) — the same "already have a
 * univocal category context" the table's own column header relies on. No tab
 * active, or the category defines no level-2 label -> today's string.
 */
export function RequestCreateAttributionSection({ form, rewardsError }: RequestCreateAttributionSectionProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const control = form.control
  const reporterId = useWatch({ control, name: 'reporter_id' })
  const rewardsValue = useWatch({ control, name: 'rewards' })

  const { categoryId: activeCategoryId } = useRequestManagementCategoryPreference()
  const { data: activeCategoryManagerLabels } = useActiveCategoryManagerLabels(activeCategoryId)
  const operatorLabel =
    activeCategoryManagerLabels?.[OPERATOR_MANAGER_LABEL_POSITION] ??
    t('requestManagement.form.create.attribution.operator')

  const sourceQuickCreate = useQuickCreateAction(SOURCES_FOR_SELECT_RESOURCE)
  const reporterQuickCreate = useQuickCreateAction(REFERENTS_FOR_SELECT_RESOURCE)
  const operatorQuickCreate = useQuickCreateAction(USERS_FOR_SELECT_RESOURCE)
  const siteQuickCreate = useQuickCreateAction(OPERATIONAL_SITES_FOR_SELECT_RESOURCE)

  // Baseline the clear-on-change below reasons against: the last Sede the
  // operator list was scoped to. Every value the field takes becomes the new
  // baseline — a pick, the Sede hydrated from a picked operator, or the actor's
  // own Sede seeded at mount (`useRequestActorAttributionDefaults`) — but only a pick runs
  // through `handleSiteItemChange`, so only a REAL change of Sede invalidates
  // the chosen operator. Synced in an effect, which runs AFTER that handler has
  // compared against the previous value.
  const previousSiteIdRef = useRef<number | null>(null)
  const siteId = useWatch({ control, name: 'operational_site_id' })
  useEffect(() => {
    previousSiteIdRef.current = siteId
  }, [siteId])
  const [autoFilledSite, setAutoFilledSite] = useState<ForSelectItem | null>(null)

  const canPickSite = can(OPERATIONAL_SITES_VIEW_ANY_PERMISSION)
  const canAssignOperator = can(ASSIGN_OPERATOR_PERMISSION)

  // Operatore -> Sede: picking an operator hydrates its own Sede from `meta`
  // (no extra fetch). An operator with no Sede leaves the current value alone,
  // and so does an actor who may not set the Sede at all — auto-filling a field
  // they cannot see would submit a key the endpoint answers 403 on.
  const handleOperatorItemChange = (item: ForSelectItem | null) => {
    const site = (item as UserForSelectItem | null)?.meta
    if (!canPickSite || site?.operational_site_id == null) return
    form.setValue('operational_site_id', site.operational_site_id, {
      shouldDirty: true,
      shouldValidate: true,
    })
    setAutoFilledSite({
      id: site.operational_site_id,
      label: site.operational_site_label ?? `#${site.operational_site_id}`,
    })
  }

  // Sede -> Operatore: a real pick/clear re-scopes the operator list, so an
  // operator from another Sede can no longer be assumed valid and is cleared
  // — only on an ACTUAL change, never on the programmatic auto-fill above.
  const handleSiteItemChange = (item: ForSelectItem | null) => {
    const nextSiteId = item?.id ?? null
    if (nextSiteId !== previousSiteIdRef.current) {
      form.setValue('operator_id', null, { shouldDirty: true })
    }
  }

  const selectLabels = {
    placeholder: t('requestManagement.form.create.attribution.selectPlaceholder'),
    empty: t('requestManagement.form.create.attribution.selectEmpty'),
    error: t('requestManagement.form.create.attribution.selectError'),
    clearLabel: t('common.clear'),
    retry: t('common.retry'),
  }

  return (
    <FormSection
      icon={Route}
      title={t('requestManagement.form.create.attribution.title')}
      description={t('requestManagement.form.create.attribution.description')}
    >
      <div className={FIELD_GRID_CLASS}>
        <FormField
          control={control}
          name="source_id"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>{t('requestManagement.form.create.attribution.source')}</FormLabel>
              <FormControl>
                <AsyncPaginatedSelect
                  resource={SOURCES_FOR_SELECT_RESOURCE}
                  value={field.value}
                  onChange={field.onChange}
                  selectedItem={sourceQuickCreate.selectedItemFor(field.value)}
                  action={sourceQuickCreate.renderAction((ref) => field.onChange(ref.id))}
                  labels={{
                    ...selectLabels,
                    searchPlaceholder: t('requestManagement.form.create.attribution.sourceSearch'),
                    triggerLabel: t('requestManagement.form.create.attribution.source'),
                  }}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <div className={FIELD_STACK_CLASS}>
          <FormField
            control={control}
            name="reporter_id"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('requestManagement.form.create.attribution.reporter')}</FormLabel>
                <FormControl>
                  <AsyncPaginatedSelect
                    resource={REFERENTS_FOR_SELECT_RESOURCE}
                    value={field.value}
                    onChange={field.onChange}
                    selectedItem={reporterQuickCreate.selectedItemFor(field.value)}
                    action={reporterQuickCreate.renderAction((ref) => field.onChange(ref.id))}
                    labels={{
                      ...selectLabels,
                      searchPlaceholder: t('requestManagement.form.create.attribution.reporterSearch'),
                      triggerLabel: t('requestManagement.form.create.attribution.reporter'),
                    }}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <ReporterRewardsField
            labelPrefix={REWARDS_LABEL_PREFIX}
            reporterId={reporterId}
            value={rewardsValue}
            onChange={(next) => form.setValue('rewards', next, { shouldDirty: true })}
            initialAssignments={EMPTY_ASSIGNMENTS}
          />
        </div>

        {canPickSite && (
          <FormField
            control={control}
            name="operational_site_id"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('requestManagement.form.create.attribution.operationalSite')}</FormLabel>
                <FormControl>
                  <AsyncPaginatedSelect
                    resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
                    value={field.value}
                    onChange={field.onChange}
                    onItemChange={handleSiteItemChange}
                    selectedItem={siteQuickCreate.selectedItemFor(field.value) ?? autoFilledSite}
                    action={siteQuickCreate.renderAction((ref) => field.onChange(ref.id))}
                    labels={{
                      ...selectLabels,
                      searchPlaceholder: t('requestManagement.form.create.attribution.operationalSiteSearch'),
                      triggerLabel: t('requestManagement.form.create.attribution.operationalSite'),
                    }}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        )}

        {/* After the Sede on purpose: the Sede is what scopes this list. */}
        {canAssignOperator && (
          <FormField
            control={control}
            name="operator_id"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{operatorLabel}</FormLabel>
                <FormControl>
                  <AsyncPaginatedSelect
                    resource={USERS_FOR_SELECT_RESOURCE}
                    value={field.value}
                    onChange={field.onChange}
                    onItemChange={handleOperatorItemChange}
                    selectedItem={operatorQuickCreate.selectedItemFor(field.value)}
                    action={operatorQuickCreate.renderAction((ref) => field.onChange(ref.id))}
                    params={siteId != null ? { operational_site_id: siteId } : undefined}
                    showAvatar
                    labels={{
                      ...selectLabels,
                      searchPlaceholder: t('requestManagement.form.create.attribution.operatorSearch'),
                      triggerLabel: operatorLabel,
                    }}
                  />
                </FormControl>
                {/* Never without the Sede field itself: the sentence describes a control the actor would not see. */}
                {canPickSite && siteId != null && (
                  <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <Info className="size-3.5 shrink-0" aria-hidden="true" />
                    {t('requestManagement.form.create.attribution.operatorFilteredBySite')}
                  </p>
                )}
                <FormMessage />
              </FormItem>
            )}
          />
        )}
      </div>

      {rewardsError && (
        <div
          role="alert"
          className="flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2.5 text-sm font-medium text-destructive"
        >
          {rewardsError}
        </div>
      )}
    </FormSection>
  )
}
