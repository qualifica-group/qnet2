import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Flag } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { TaskStatusRows } from '@/features/task-statuses/task-status-rows'
import { useTaskStatusForm } from '@/features/task-statuses/use-task-status-form'
import type { TaskStatusDetail } from '@/features/task-statuses/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const TASK_PRIORITY_FORM_ID = 'task-status-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface TaskStatusFormBodyProps {
  onSuccess: (taskStatus: TaskStatusDetail) => void
  onCancel: () => void
}

/**
 * The task status create form as a replica of the detail (wave 3 of the "no
 * edit pages" plan): the same rows, closed until clicked (Fatto/Ripristina,
 * nothing saved per row); Salva validates and creates everything at once.
 */
export function TaskStatusFormBody({ onSuccess, onCancel }: TaskStatusFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useTaskStatusForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('taskStatuses.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={TASK_PRIORITY_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('taskStatuses.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Flag />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('taskStatuses.form.save'),
          saving: t('taskStatuses.form.saving'),
          cancel: t('taskStatuses.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('taskStatuses.form.sections.identity.title')} full>
            <RecordFieldList>
              <TaskStatusRows inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
