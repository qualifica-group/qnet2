import { useTranslation } from 'react-i18next'
import { Route } from 'lucide-react'
import { useWatch, type UseFormReturn } from 'react-hook-form'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { FormSection } from '@/components/form-section'
import { RewardAssignmentField } from '@/features/opportunities/reward-assignment-field'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import type { RequestCreateFormValues } from '@/features/request-management/request-create-schema'

interface RequestCreateAttributionSectionProps {
  form: UseFormReturn<RequestCreateFormValues>
  /** Server 422 for the reward/reporter D-3 guard, collected as one banner (see `useRequestCreateForm`). */
  rewardsError: string | null
}

/**
 * The create form's attribution section (user directive 2026-07-24): the same
 * "Fonte"/"Segnalatore"/"buono" trio the work panel's view already carries,
 * available at creation so a request can be attributed up front. Both pickers
 * are the plain `AsyncPaginatedSelect` (not the work panel's meta-driven
 * `RelationSelectField`): this create-only form has no `permissions` envelope
 * to gate fields against — creation is gated wholesale by
 * `request-management.create` server-side — so it mirrors the registry picker
 * of `RequestCreateClientSection` instead.
 *
 * The reward control (spec 0059 D-3) sits under the Segnalatore because the
 * beneficiary is always that reporter: it disables itself with an accessible
 * hint until a reporter is chosen.
 */
export function RequestCreateAttributionSection({ form, rewardsError }: RequestCreateAttributionSectionProps) {
  const { t } = useTranslation()
  const control = form.control
  const reporterId = useWatch({ control, name: 'reporter_id' })
  const rewardsValue = useWatch({ control, name: 'rewards' })

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
              <FormLabel>{t('requestManagement.form.create.attribution.source')}</FormLabel>
              <FormControl>
                <AsyncPaginatedSelect
                  resource={SOURCES_FOR_SELECT_RESOURCE}
                  value={field.value}
                  onChange={field.onChange}
                  labels={{
                    placeholder: t('requestManagement.form.create.attribution.selectPlaceholder'),
                    searchPlaceholder: t('requestManagement.form.create.attribution.sourceSearch'),
                    empty: t('requestManagement.form.create.attribution.selectEmpty'),
                    error: t('requestManagement.form.create.attribution.selectError'),
                    clearLabel: t('common.clear'),
                    triggerLabel: t('requestManagement.form.create.attribution.source'),
                    retry: t('common.retry'),
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
                    labels={{
                      placeholder: t('requestManagement.form.create.attribution.selectPlaceholder'),
                      searchPlaceholder: t('requestManagement.form.create.attribution.reporterSearch'),
                      empty: t('requestManagement.form.create.attribution.selectEmpty'),
                      error: t('requestManagement.form.create.attribution.selectError'),
                      clearLabel: t('common.clear'),
                      triggerLabel: t('requestManagement.form.create.attribution.reporter'),
                      retry: t('common.retry'),
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
