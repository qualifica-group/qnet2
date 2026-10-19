import { useTranslation } from 'react-i18next'
import { useFormContext } from 'react-hook-form'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import { NumberInlineRow, SwitchInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { Badge } from '@/components/ui/badge'
import { JOB_ROLES_FOR_SELECT_RESOURCE } from '@/features/job-roles/for-select-api'
import { JOB_ROLES_DOMAIN } from '@/features/job-roles/types'
import {
  VALIDITY_MONTHS_MAX,
  VALIDITY_MONTHS_MIN,
  type StaffCourseFormValues,
} from '@/features/staff-courses/staff-course-schema'
import type { StaffCourseJobRole } from '@/features/staff-courses/types'

interface StaffCourseRowsProps {
  inline: InlineEdit
  canEdit: boolean
  /** The required job roles as named records: from the saved detail, or resolved from the draft's ids. */
  jobRoles: StaffCourseJobRole[]
}

/** The job roles requiring the course, synced as a whole set: badges when closed, the multi picker when open. */
function StaffCourseJobRolesRow({ inline, canEdit, jobRoles }: StaffCourseRowsProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<StaffCourseFormValues>()

  return (
    <RecordInlineField
      field="job_role_ids"
      label={t('staffCourses.form.jobRoles')}
      inline={inline}
      canEdit={canEdit}
      editor={
        <RelationMultiSelectField
          control={control}
          name="job_role_ids"
          metaKey="job_role_ids"
          label={t('staffCourses.form.jobRoles')}
          resource={JOB_ROLES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('staffCourses.form.jobRolesSearch')}
          selected={jobRoles}
          placeholder={t('staffCourses.form.jobRolesPlaceholder')}
          emptyLabel={t('staffCourses.form.jobRolesEmpty')}
          errorLabel={t('staffCourses.form.jobRolesError')}
          removeLabel={t('staffCourses.form.jobRolesRemove')}
          retryLabel={t('common.retry')}
          description={t('staffCourses.form.jobRolesHint')}
        />
      }
    >
      {jobRoles.length > 0 ? (
        <div className="flex flex-wrap gap-1.5">
          {jobRoles.map((role) => (
            <Badge key={role.id} variant="outline" className="max-w-full">
              <RecordLink domain={JOB_ROLES_DOMAIN} id={role.id}>
                {role.name}
              </RecordLink>
            </Badge>
          ))}
        </div>
      ) : (
        <DetailEmpty />
      )}
    </RecordInlineField>
  )
}

/** The course's rows, shared by the detail (saved one at a time) and the create draft (kept until Save). */
export function StaffCourseRows({ inline, canEdit, jobRoles }: StaffCourseRowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<StaffCourseFormValues> name="name" label={t('staffCourses.form.name')} inline={inline} canEdit={canEdit} />
      <TextInlineRow<StaffCourseFormValues>
        name="description"
        label={t('staffCourses.form.description')}
        inline={inline}
        canEdit={canEdit}
        multiline
      />
      <TextInlineRow<StaffCourseFormValues>
        name="duration_hours"
        label={t('staffCourses.form.durationHours')}
        hint={t('staffCourses.form.durationHint')}
        inline={inline}
        canEdit={canEdit}
        suffix={t('staffCourses.form.hoursUnit')}
      />
      <NumberInlineRow<StaffCourseFormValues>
        name="validity_months"
        label={t('staffCourses.form.validityMonths')}
        hint={t('staffCourses.form.validityHint')}
        inline={inline}
        canEdit={canEdit}
        min={VALIDITY_MONTHS_MIN}
        max={VALIDITY_MONTHS_MAX}
        step={1}
        suffix={t('staffCourses.form.monthsUnit')}
      />
      <SwitchInlineRow<StaffCourseFormValues>
        name="is_active"
        label={t('staffCourses.form.isActive')}
        inline={inline}
        canEdit={canEdit}
      />
      <StaffCourseJobRolesRow inline={inline} canEdit={canEdit} jobRoles={jobRoles} />
    </>
  )
}
