import { useTranslation } from 'react-i18next'
import { useFieldArray, useFormContext, useFormState, useWatch } from 'react-hook-form'
import { Plus, Trash2 } from 'lucide-react'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { JOB_ROLES_FOR_SELECT_RESOURCE } from '@/features/job-roles/for-select-api'
import { SKILL_LEVEL_MIN, SKILL_LEVELS, isSkillLevel } from '@/features/skills/skill-level'
import type { SkillFormValues } from '@/features/skills/skill-schema'
import type { SkillJobRole } from '@/features/skills/types'

interface SkillJobRolesEditorProps {
  /** Known names of the saved job roles, so the pickers show them before their list loads. */
  jobRoles: SkillJobRole[]
}

/**
 * The editor of the required job roles: one row per job role (picker + minimum level), rows are
 * added and removed. The same job role twice is refused by the schema, shown under the list.
 */
export function SkillJobRolesEditor({ jobRoles }: SkillJobRolesEditorProps) {
  const { t } = useTranslation()
  const { control, setValue } = useFormContext<SkillFormValues>()
  const { fields, append, remove } = useFieldArray({ control, name: 'job_roles' })
  // `fields` only keys the rows: their live values come from the watch (setValue does not refresh `fields`).
  const rows = useWatch({ control, name: 'job_roles' })
  const { errors } = useFormState({ control, name: 'job_roles' })
  const message = errors.job_roles?.message ?? errors.job_roles?.root?.message

  return (
    <div className="flex flex-col gap-2">
      {fields.map((field, index) => {
        const row = rows[index] ?? field
        const known = jobRoles.find((role) => role.id === row.job_role_id)
        return (
          <div key={field.id} className="flex flex-wrap items-center gap-2 sm:flex-nowrap">
            <div className="min-w-0 flex-1 basis-full sm:basis-auto">
              <AsyncPaginatedSelect
                resource={JOB_ROLES_FOR_SELECT_RESOURCE}
                value={row.job_role_id}
                selectedItem={known ? { id: known.id, label: known.name } : null}
                onChange={(value) => setValue(`job_roles.${index}.job_role_id`, value, { shouldDirty: true })}
                labels={{
                  placeholder: t('skills.form.jobRolePlaceholder'),
                  searchPlaceholder: t('skills.form.jobRoleSearch'),
                  empty: t('skills.form.jobRoleEmpty'),
                  error: t('skills.form.jobRoleError'),
                  clearLabel: t('common.clear'),
                  triggerLabel: t('skills.form.jobRole'),
                  retry: t('common.retry'),
                }}
              />
            </div>
            <Select
              value={String(row.min_level)}
              onValueChange={(value) => {
                const level = Number(value)
                setValue(`job_roles.${index}.min_level`, isSkillLevel(level) ? level : SKILL_LEVEL_MIN, { shouldDirty: true })
              }}
            >
              <SelectTrigger className="w-44" aria-label={t('skills.form.minLevel')}>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {SKILL_LEVELS.map((level) => (
                  <SelectItem key={level} value={String(level)}>
                    {level} · {t(`skills.levels.${level}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Button
              type="button"
              variant="ghost"
              size="icon-xs"
              aria-label={t('skills.form.removeJobRole')}
              onClick={() => remove(index)}
            >
              <Trash2 aria-hidden="true" />
            </Button>
          </div>
        )
      })}
      <div>
        <Button
          type="button"
          size="xs"
          variant="outline"
          onClick={() => append({ job_role_id: null, min_level: SKILL_LEVEL_MIN })}
        >
          <Plus aria-hidden="true" />
          {t('skills.form.addJobRole')}
        </Button>
      </div>
      {typeof message === 'string' ? (
        <p role="alert" className="text-xs font-medium text-destructive">
          {message}
        </p>
      ) : null}
    </div>
  )
}
