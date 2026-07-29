import { useTranslation } from 'react-i18next'
import { Route } from 'lucide-react'
import { useWatch, type UseFormReturn } from 'react-hook-form'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { FormSection } from '@/components/form-section'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import { useAbilities } from '@/features/auth/use-abilities'
import { RewardAssignmentField } from '@/features/opportunities/reward-assignment-field'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import type { RequestCreateFormValues } from '@/features/request-management/request-create-schema'

/** Supervisory ability gating the Operatore field (mirrors RequestManagementPolicy::assignOperator). */
const ASSIGN_OPERATOR_PERMISSION = 'request-management.assignOperator'

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
 * The Operatore is the one field NOT covered by `request-management.create`:
 * deciding who works a request is supervisory, so it is rendered only for an
 * actor holding `request-management.assignOperator` — the same ability the
 * endpoint enforces server-side. Unlike the work panel's own operator picker
 * this list is unscoped: the create form has no Sede field to scope it by.
 *
 * The reward control (spec 0059 D-3) sits under the Segnalatore because the
 * beneficiary is always that reporter: it disables itself with an accessible
 * hint until a reporter is chosen.
 */
export function RequestCreateAttributionSection({ form, rewardsError }: RequestCreateAttributionSectionProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const control = form.control
  const reporterId = useWatch({ control, name: 'reporter_id' })
  const rewardsValue = useWatch({ control, name: 'rewards' })

  const sourceQuickCreate = useQuickCreateAction(SOURCES_FOR_SELECT_RESOURCE)
  const reporterQuickCreate = useQuickCreateAction(REFERENTS_FOR_SELECT_RESOURCE)
  const operatorQuickCreate = useQuickCreateAction(USERS_FOR_SELECT_RESOURCE)

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
      <div className="grid gap-3 @2xl:grid-cols-2">
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

        <div className="flex flex-col gap-1.5">
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
          <RewardAssignmentField
            value={rewardsValue}
            onChange={(next) => form.setValue('rewards', next, { shouldDirty: true })}
            initialAssignments={[]}
            reporterId={reporterId}
            fieldLabel={t('requestManagement.form.create.attribution.rewards.fieldLabel')}
            disabledHint={t('requestManagement.form.create.attribution.rewards.reporterRequiredHint')}
            addLabel={t('requestManagement.form.create.attribution.rewards.add')}
            removeLabel={(name) =>
              t('requestManagement.form.create.attribution.rewards.remove', { name, defaultValue: `Remove ${name}` })
            }
            searchPlaceholder={t('requestManagement.form.create.attribution.rewards.searchPlaceholder')}
            emptyLabel={t('requestManagement.form.create.attribution.rewards.empty')}
            errorLabel={t('requestManagement.form.create.attribution.rewards.error')}
            retryLabel={t('common.retry')}
            loadMoreLabel={t('requestManagement.form.create.attribution.rewards.loadMore')}
          />
        </div>

        {can(ASSIGN_OPERATOR_PERMISSION) && (
          <FormField
            control={control}
            name="operator_id"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('requestManagement.form.create.attribution.operator')}</FormLabel>
                <FormControl>
                  <AsyncPaginatedSelect
                    resource={USERS_FOR_SELECT_RESOURCE}
                    value={field.value}
                    onChange={field.onChange}
                    selectedItem={operatorQuickCreate.selectedItemFor(field.value)}
                    action={operatorQuickCreate.renderAction((ref) => field.onChange(ref.id))}
                    showAvatar
                    labels={{
                      ...selectLabels,
                      searchPlaceholder: t('requestManagement.form.create.attribution.operatorSearch'),
                      triggerLabel: t('requestManagement.form.create.attribution.operator'),
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
