import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { SignalHigh } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { TaskPriorityRows } from '@/features/task-priorities/task-priority-rows'
import { useTaskPriorityForm } from '@/features/task-priorities/use-task-priority-form'
import type { TaskPriorityDetail } from '@/features/task-priorities/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const TASK_PRIORITY_FORM_ID = 'task-priority-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface TaskPriorityFormBodyProps {
  onSuccess: (taskPriority: TaskPriorityDetail) => void
  onCancel: () => void
}

/**
 * The task priority create form as a replica of the detail (wave 3 of the "no
 * edit pages" plan): the same rows, closed until clicked (Fatto/Ripristina,
 * nothing saved per row); Salva validates and creates everything at once.
 */
export function TaskPriorityFormBody({ onSuccess, onCancel }: TaskPriorityFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useTaskPriorityForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('taskPriorities.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={TASK_PRIORITY_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('taskPriorities.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<SignalHigh />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('taskPriorities.form.save'),
          saving: t('taskPriorities.form.saving'),
          cancel: t('taskPriorities.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('taskPriorities.form.sections.identity.title')} full>
            <RecordFieldList>
              <TaskPriorityRows inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
