import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { ListChecks } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { JobActivityRows } from '@/features/job-activities/job-activity-rows'
import { useJobActivityForm } from '@/features/job-activities/use-job-activity-form'
import type { JobActivityDetail } from '@/features/job-activities/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const FORM_ID = 'job-activity-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface JobActivityFormBodyProps {
  onSuccess: (jobActivity: JobActivityDetail) => void
  onCancel: () => void
}

/**
 * The activity create form as a replica of the detail: the same rows, closed
 * until clicked (nothing saved per row); Save validates and creates everything
 * at once.
 */
export function JobActivityFormBody({ onSuccess, onCancel }: JobActivityFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useJobActivityForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('jobActivities.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('jobActivities.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<ListChecks />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('jobActivities.form.save'),
          saving: t('jobActivities.form.saving'),
          cancel: t('jobActivities.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('jobActivities.form.sections.identity.title')} full>
            <RecordFieldList>
              <JobActivityRows inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
