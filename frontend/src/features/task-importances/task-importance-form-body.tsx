import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Star } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { TaskImportanceRows } from '@/features/task-importances/task-importance-rows'
import { useTaskImportanceForm } from '@/features/task-importances/use-task-importance-form'
import type { TaskImportanceDetail } from '@/features/task-importances/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const TASK_PRIORITY_FORM_ID = 'task-importance-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface TaskImportanceFormBodyProps {
  onSuccess: (taskImportance: TaskImportanceDetail) => void
  onCancel: () => void
}

/**
 * The task importance create form as a replica of the detail (wave 3 of the "no
 * edit pages" plan): the same rows, closed until clicked (Fatto/Ripristina,
 * nothing saved per row); Salva validates and creates everything at once.
 */
export function TaskImportanceFormBody({ onSuccess, onCancel }: TaskImportanceFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useTaskImportanceForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('taskImportances.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={TASK_PRIORITY_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('taskImportances.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Star />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('taskImportances.form.save'),
          saving: t('taskImportances.form.saving'),
          cancel: t('taskImportances.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('taskImportances.form.sections.identity.title')} full>
            <RecordFieldList>
              <TaskImportanceRows inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
