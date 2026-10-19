import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Briefcase } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { JobRoleRows } from '@/features/job-roles/job-role-rows'
import { useJobRoleDraftActivities } from '@/features/job-roles/use-job-role-draft-activities'
import { useJobRoleForm } from '@/features/job-roles/use-job-role-form'
import type { JobRoleDetail } from '@/features/job-roles/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const FORM_ID = 'job-role-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface JobRoleFormBodyProps {
  onSuccess: (jobRole: JobRoleDetail) => void
  onCancel: () => void
}

/**
 * The job role create form as a replica of the detail: the same rows, closed
 * until clicked (nothing saved per row); Save validates and creates everything
 * at once.
 */
export function JobRoleFormBody({ onSuccess, onCancel }: JobRoleFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useJobRoleForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const jobActivities = useJobRoleDraftActivities(form.control)
  const heading = name.trim() || t('jobRoles.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('jobRoles.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Briefcase />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('jobRoles.form.save'),
          saving: t('jobRoles.form.saving'),
          cancel: t('jobRoles.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('jobRoles.form.sections.identity.title')} full>
            <RecordFieldList>
              <JobRoleRows inline={draft} canEdit jobActivities={jobActivities} />
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
