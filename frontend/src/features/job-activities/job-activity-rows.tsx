import { useTranslation } from 'react-i18next'
import { TextInlineRow } from '@/components/record-form/inline-rows'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { JobActivityFormValues } from '@/features/job-activities/use-job-activity-form'

interface JobActivityRowsProps {
  inline: InlineEdit
  canEdit: boolean
}

/** The job activity's rows, shared by the detail (saved one at a time) and the create draft (kept until Save). */
export function JobActivityRows({ inline, canEdit }: JobActivityRowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<JobActivityFormValues> name="name" label={t('jobActivities.form.name')} inline={inline} canEdit={canEdit} />
      <TextInlineRow<JobActivityFormValues>
        name="description"
        label={t('jobActivities.form.description')}
        inline={inline}
        canEdit={canEdit}
        multiline
      />
    </>
  )
}
