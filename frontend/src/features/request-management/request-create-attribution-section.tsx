import { useTranslation } from 'react-i18next'
import { Route } from 'lucide-react'
import { useWatch, type UseFormReturn } from 'react-hook-form'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { FormSection } from '@/components/form-section'
import { ReporterRewardsField } from '@/components/record-form/reporter-rewards-field'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import type { ForSelectItem } from '@/features/for-select/types'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { FIELD_GRID_CLASS, FIELD_STACK_CLASS } from '@/components/record-form/layout'
import type { RequestCreateFormValues } from '@/features/request-management/request-create-schema'
import type { RewardAssignmentRef } from '@/features/rewards/types'

/** i18n root of the reward block's strings, resolved inside `RequestRewardsField`. */
const REWARDS_LABEL_PREFIX = 'requestManagement.form.create.attribution.rewards'

/** Hoisted: nothing is persisted yet on a create form, and a fresh `[]` per render would reseed the chips' cache. */
const EMPTY_ASSIGNMENTS: RewardAssignmentRef[] = []

interface RequestCreateAttributionSectionProps {
  form: UseFormReturn<RequestCreateFormValues>
  /** Server 422 for the reward/reporter D-3 guard, collected as one banner (see `useRequestCreateForm`). */
  rewardsError: string | null
  /**
   * Whether the actor may set the Sede (`operational-sites.viewAny`), resolved
   * once by the form that owns it: attributing a request is supervisory, so
   * the field is rendered only for an actor holding the very ability the store
   * endpoint enforces server-side (user directive 2026-08-03).
   */
  canPickSite: boolean
  /**
   * The operator half of the Sede <-> Operatore link the FORM owns
   * (`useRequestSiteOperatorLink`, spec 0097 rev-2 D-7): the slot the Sede
   * scopes lives in the "Team" section since rev-2, so this one only reports
   * the Sede it just took and shows the one hydrated from a picked operator.
   */
  autoFilledSite: ForSelectItem | null
  onSiteItemChange: (item: ForSelectItem | null) => void
}

/**
 * The create form's attribution section (user directive 2026-07-24): the same
 * "Fonte"/"Segnalatore"/"buono" trio the work panel's view already carries,
 * plus the Sede operativa, available at creation so a request can be
 * attributed up front. The relation pickers are the plain
 * `AsyncPaginatedSelect` (not the work panel's meta-driven
 * `RelationSelectField`): this create-only form has no `permissions` envelope
 * to gate fields against — creation is gated wholesale by
 * `request-management.create` server-side — so it mirrors the registry picker
 * of `RequestCreateClientSection` instead. Each relation carries the shared
 * quick-create "+" (spec 0028) so a missing Fonte/Segnalatore/sede can be
 * created without leaving the form.
 *
 * Fonte is REQUIRED (user directive 2026-07-29), mirroring
 * `StoreRequestRequest`'s own `required` rule.
 *
 * The team left this section with spec 0097 rev-2 D-7
 * (`request-create-team-section.tsx`). The Sede stayed — it is attribution,
 * not team — but it is also what SCOPES the operator slot, now one section
 * away: the link is therefore cabled by whoever owns the form
 * (`useRequestSiteOperatorLink`), never here.
 *
 * The reward control (spec 0059 D-3) sits under the Segnalatore because the
 * beneficiary is always that reporter, and is mounted only once there IS one:
 * `RequestRewardsField` owns that rule for this form and the work panel alike.
 */
export function RequestCreateAttributionSection({
  form,
  rewardsError,
  canPickSite,
  autoFilledSite,
  onSiteItemChange,
}: RequestCreateAttributionSectionProps) {
  const { t } = useTranslation()
  const control = form.control
  const reporterId = useWatch({ control, name: 'reporter_id' })
  const rewardsValue = useWatch({ control, name: 'rewards' })

  const sourceQuickCreate = useQuickCreateAction(SOURCES_FOR_SELECT_RESOURCE)
  const reporterQuickCreate = useQuickCreateAction(REFERENTS_FOR_SELECT_RESOURCE)
  const siteQuickCreate = useQuickCreateAction(OPERATIONAL_SITES_FOR_SELECT_RESOURCE)

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
                    onItemChange={onSiteItemChange}
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
