import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { SwitchInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { Badge } from '@/components/ui/badge'
import { JOB_ROLES_DOMAIN } from '@/features/job-roles/types'
import { SkillJobRolesEditor } from '@/features/skills/skill-job-roles-editor'
import { SkillLevelBadge } from '@/features/skills/skill-level-badge'
import type { SkillFormValues } from '@/features/skills/skill-schema'
import type { SkillJobRole } from '@/features/skills/types'

interface SkillRowsProps {
  inline: InlineEdit
  canEdit: boolean
  /** The required job roles as named records: from the saved detail, or resolved from the draft's rows. */
  jobRoles: SkillJobRole[]
}

/** The job roles requiring the skill with their minimum level, synced as a whole set. */
function SkillJobRolesRow({ inline, canEdit, jobRoles }: SkillRowsProps) {
  const { t } = useTranslation()

  return (
    <RecordInlineField
      field="job_roles"
      label={t('skills.form.jobRoles')}
      inline={inline}
      canEdit={canEdit}
      editor={<SkillJobRolesEditor jobRoles={jobRoles} />}
    >
      {jobRoles.length > 0 ? (
        <ul className="flex flex-col gap-1.5">
          {jobRoles.map((role) => (
            <li key={role.id} className="flex flex-wrap items-center gap-2">
              <Badge variant="outline" className="max-w-full">
                <RecordLink domain={JOB_ROLES_DOMAIN} id={role.id}>
                  {role.name}
                </RecordLink>
              </Badge>
              <span className="text-xs text-muted-foreground">{t('skills.form.minLevelShort')}</span>
              <SkillLevelBadge level={role.min_level} />
            </li>
          ))}
        </ul>
      ) : (
        <DetailEmpty />
      )}
    </RecordInlineField>
  )
}

/** The skill's rows, shared by the detail (saved one at a time) and the create draft (kept until Save). */
export function SkillRows({ inline, canEdit, jobRoles }: SkillRowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<SkillFormValues> name="name" label={t('skills.form.name')} inline={inline} canEdit={canEdit} />
      <TextInlineRow<SkillFormValues>
        name="category"
        label={t('skills.form.category')}
        hint={t('skills.form.categoryHint')}
        inline={inline}
        canEdit={canEdit}
      />
      <TextInlineRow<SkillFormValues>
        name="description"
        label={t('skills.form.description')}
        inline={inline}
        canEdit={canEdit}
        multiline
      />
      <SwitchInlineRow<SkillFormValues> name="is_active" label={t('skills.form.isActive')} inline={inline} canEdit={canEdit} />
      <SkillJobRolesRow inline={inline} canEdit={canEdit} jobRoles={jobRoles} />
    </>
  )
}
