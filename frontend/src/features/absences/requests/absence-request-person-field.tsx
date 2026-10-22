import { useTranslation } from 'react-i18next'
import { useFormContext } from 'react-hook-form'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { useSelectLabels } from '@/features/absences/balances/use-select-labels'
import type { AbsenceRequestFormValues } from '@/features/absences/requests/absence-request-schema'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'

/** Person the request is for: who holds `absence-requests.manageAll` may ask on behalf of somebody else. */
export function AbsenceRequestPersonField() {
  const { t } = useTranslation()
  const { control } = useFormContext<AbsenceRequestFormValues>()
  const labels = {
    ...useSelectLabels(),
    placeholder: t('absences.requests.form.personPlaceholder'),
    searchPlaceholder: t('absences.requests.form.personSearch'),
    empty: t('absences.requests.form.personEmpty'),
    error: t('absences.requests.form.personError'),
    triggerLabel: t('absences.requests.form.person'),
  }

  return (
    <FormField
      control={control}
      name="user_id"
      render={({ field }) => (
        <FormItem>
          <FormLabel>{t('absences.requests.form.person')}</FormLabel>
          <FormControl>
            <AsyncPaginatedSelect
              resource={USERS_FOR_SELECT_RESOURCE}
              value={field.value}
              onChange={field.onChange}
              labels={labels}
              showAvatar
            />
          </FormControl>
          <FormDescription className="text-xs">{t('absences.requests.form.personHint')}</FormDescription>
          <FormMessage />
        </FormItem>
      )}
    />
  )
}
