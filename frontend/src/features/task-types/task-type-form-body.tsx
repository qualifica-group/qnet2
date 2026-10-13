import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Shapes } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { TaskTypeRows } from '@/features/task-types/task-type-rows'
import { useTaskTypeForm } from '@/features/task-types/use-task-type-form'
import type { TaskTypeDetail } from '@/features/task-types/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const TASK_PRIORITY_FORM_ID = 'task-type-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface TaskTypeFormBodyProps {
  onSuccess: (taskType: TaskTypeDetail) => void
  onCancel: () => void
}

/**
 * The task type create form as a replica of the detail (wave 3 of the "no
 * edit pages" plan): the same rows, closed until clicked (Fatto/Ripristina,
 * nothing saved per row); Salva validates and creates everything at once.
 */
export function TaskTypeFormBody({ onSuccess, onCancel }: TaskTypeFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useTaskTypeForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('taskTypes.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={TASK_PRIORITY_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('taskTypes.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Shapes />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('taskTypes.form.save'),
          saving: t('taskTypes.form.saving'),
          cancel: t('taskTypes.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('taskTypes.form.sections.identity.title')} full>
            <RecordFieldList>
              <TaskTypeRows inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
