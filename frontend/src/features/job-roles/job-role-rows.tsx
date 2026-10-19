import { useTranslation } from 'react-i18next'
import { useFormContext } from 'react-hook-form'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import { TextInlineRow } from '@/components/record-form/inline-rows'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { Badge } from '@/components/ui/badge'
import { JOB_ACTIVITIES_FOR_SELECT_RESOURCE } from '@/features/job-activities/for-select-api'
import type { JobRoleFormValues } from '@/features/job-roles/use-job-role-form'
import type { JobRoleActivity } from '@/features/job-roles/types'

interface JobRoleRowsProps {
  inline: InlineEdit
  canEdit: boolean
  /** The activities as named records: from the saved detail, or resolved from the draft's ids. */
  jobActivities: JobRoleActivity[]
}

/** The linked activities, synced as a whole set: linked badges when closed, the multi picker when open. */
function JobRoleJobActivitiesRow({ inline, canEdit, jobActivities }: JobRoleRowsProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<JobRoleFormValues>()

  return (
    <RecordInlineField
      field="job_activity_ids"
      label={t('jobRoles.form.jobActivities')}
      inline={inline}
      canEdit={canEdit}
      editor={
        <RelationMultiSelectField
          control={control}
          name="job_activity_ids"
          metaKey="job_activity_ids"
          label={t('jobRoles.form.jobActivities')}
          resource={JOB_ACTIVITIES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('jobRoles.form.jobActivitiesSearch')}
          selected={jobActivities.map((activity) => ({ id: activity.id, name: activity.name }))}
          placeholder={t('jobRoles.form.jobActivitiesPlaceholder')}
          emptyLabel={t('jobRoles.form.jobActivitiesEmpty')}
          errorLabel={t('jobRoles.form.jobActivitiesError')}
          removeLabel={t('jobRoles.form.jobActivitiesRemove')}
          retryLabel={t('common.retry')}
        />
      }
    >
      {jobActivities.length > 0 ? (
        <div className="flex flex-wrap gap-1.5">
          {jobActivities.map((activity) => (
            <Badge key={activity.id} variant="outline" className="max-w-full">
              <RecordLink domain="job-activities" id={activity.id}>
                {activity.name}
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

/** The job role's rows, shared by the detail (saved one at a time) and the create draft (kept until Save). */
export function JobRoleRows({ inline, canEdit, jobActivities }: JobRoleRowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<JobRoleFormValues> name="name" label={t('jobRoles.form.name')} inline={inline} canEdit={canEdit} />
      <TextInlineRow<JobRoleFormValues>
        name="description"
        label={t('jobRoles.form.description')}
        inline={inline}
        canEdit={canEdit}
        multiline
      />
      <JobRoleJobActivitiesRow inline={inline} canEdit={canEdit} jobActivities={jobActivities} />
    </>
  )
}
