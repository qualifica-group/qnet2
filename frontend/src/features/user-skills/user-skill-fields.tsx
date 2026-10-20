import { useTranslation } from 'react-i18next'
import { useFormContext } from 'react-hook-form'
import { AsyncPaginatedSelect, type AsyncPaginatedSelectLabels } from '@/components/ui/async-paginated-select'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { dayOf } from '@/features/absences/calendar/calendar-month'
import type { ForSelectItem } from '@/features/for-select/types'
import { SKILL_LEVELS } from '@/features/skills/skill-level'
import type { UserSkillFormValues } from '@/features/user-skills/user-skill-schema'

const USERS_RESOURCE = 'users'
const SKILLS_RESOURCE = 'skills'

interface UserSkillFieldsProps {
  /** Known person/skill of the edited row, so the triggers show their names before the lists load. */
  selectedUser?: ForSelectItem
  selectedSkill?: ForSelectItem
}

/** Person, skill, level, assessment day and notes: the fields of the person skill form. */
export function UserSkillFields({ selectedUser, selectedSkill }: UserSkillFieldsProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<UserSkillFormValues>()

  const pickerLabels = (prefix: 'user' | 'skill'): AsyncPaginatedSelectLabels => ({
    placeholder: t(`userSkills.form.${prefix}Placeholder`),
    searchPlaceholder: t(`userSkills.form.${prefix}Search`),
    empty: t(`userSkills.form.${prefix}Empty`),
    error: t(`userSkills.form.${prefix}Error`),
    clearLabel: t('common.clear'),
    triggerLabel: t(`userSkills.form.${prefix}`),
    retry: t('common.retry'),
  })

  return (
    <div className="space-y-3">
      <FormField
        control={control}
        name="user_id"
        render={({ field }) => (
          <FormItem>
            <FormLabel required>{t('userSkills.form.user')}</FormLabel>
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
        name="skill_id"
        render={({ field }) => (
          <FormItem>
            <FormLabel required>{t('userSkills.form.skill')}</FormLabel>
            <FormControl>
              <AsyncPaginatedSelect
                resource={SKILLS_RESOURCE}
                value={field.value}
                onChange={field.onChange}
                selectedItem={selectedSkill}
                labels={pickerLabels('skill')}
              />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />
      <div className="grid gap-3 sm:grid-cols-2">
        <FormField
          control={control}
          name="level"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>{t('userSkills.form.level')}</FormLabel>
              <Select
                value={field.value === null ? '' : String(field.value)}
                onValueChange={(value) => field.onChange(Number(value))}
              >
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder={t('userSkills.form.levelPlaceholder')} />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {SKILL_LEVELS.map((level) => (
                    <SelectItem key={level} value={String(level)}>
                      {level} · {t(`skills.levels.${level}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={control}
          name="assessed_on"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>{t('userSkills.form.assessedOn')}</FormLabel>
              <FormControl>
                <Input type="date" max={dayOf(new Date())} {...field} />
              </FormControl>
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
            <FormLabel>{t('userSkills.form.notes')}</FormLabel>
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
