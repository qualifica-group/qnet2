import { useTranslation } from 'react-i18next'
import { useFormContext } from 'react-hook-form'
import { AsyncPaginatedSelect, type AsyncPaginatedSelectLabels } from '@/components/ui/async-paginated-select'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { dayOf } from '@/features/absences/calendar/calendar-month'
import type { ForSelectItem } from '@/features/for-select/types'
import type { StaffTrainingFormValues } from '@/features/staff-trainings/staff-training-schema'
import { STAFF_TRAINING_OUTCOMES } from '@/features/staff-trainings/types'

const USERS_RESOURCE = 'users'
const STAFF_COURSES_RESOURCE = 'staff-courses'

interface StaffTrainingFieldsProps {
  /** Known person/course of the edited training, so the triggers show their names before the lists load. */
  selectedUser?: ForSelectItem
  selectedCourse?: ForSelectItem
}

/** Person, course, completion day, outcome and notes: the fields of the training form. */
export function StaffTrainingFields({ selectedUser, selectedCourse }: StaffTrainingFieldsProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<StaffTrainingFormValues>()

  const pickerLabels = (prefix: 'user' | 'course'): AsyncPaginatedSelectLabels => ({
    placeholder: t(`staffTrainings.form.${prefix}Placeholder`),
    searchPlaceholder: t(`staffTrainings.form.${prefix}Search`),
    empty: t(`staffTrainings.form.${prefix}Empty`),
    error: t(`staffTrainings.form.${prefix}Error`),
    clearLabel: t('common.clear'),
    triggerLabel: t(`staffTrainings.form.${prefix}`),
    retry: t('common.retry'),
  })

  return (
    <div className="space-y-3">
      <FormField
        control={control}
        name="user_id"
        render={({ field }) => (
          <FormItem>
            <FormLabel required>{t('staffTrainings.form.user')}</FormLabel>
            <FormControl>
              <AsyncPaginatedSelect
                resource={USERS_RESOURCE}
                value={field.value}
                onChange={field.onChange}
                selectedItem={selectedUser}
                labels={pickerLabels('user')}
                showAvatar
              />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />
      <FormField
        control={control}
        name="staff_course_id"
        render={({ field }) => (
          <FormItem>
            <FormLabel required>{t('staffTrainings.form.course')}</FormLabel>
            <FormControl>
              <AsyncPaginatedSelect
                resource={STAFF_COURSES_RESOURCE}
                value={field.value}
                onChange={field.onChange}
                selectedItem={selectedCourse}
                labels={pickerLabels('course')}
              />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />
      <div className="grid gap-3 sm:grid-cols-2">
        <FormField
          control={control}
          name="completed_on"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>{t('staffTrainings.form.completedOn')}</FormLabel>
              <FormControl>
                <Input type="date" max={dayOf(new Date())} {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={control}
          name="outcome"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>{t('staffTrainings.form.outcome')}</FormLabel>
              <Select value={field.value} onValueChange={field.onChange}>
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {STAFF_TRAINING_OUTCOMES.map((outcome) => (
                    <SelectItem key={outcome} value={outcome}>
                      {t(`enums.staff_training_outcome.${outcome}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FormMessage />
            </FormItem>
          )}
        />
      </div>
      <FormField
        control={control}
        name="notes"
        render={({ field }) => (
          <FormItem>
            <FormLabel>{t('staffTrainings.form.notes')}</FormLabel>
            <FormControl>
              <Textarea rows={3} {...field} />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />
    </div>
  )
}
