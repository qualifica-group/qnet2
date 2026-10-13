import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Tags } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { TaskCategoryRows } from '@/features/task-categories/task-category-rows'
import { useTaskCategoryForm } from '@/features/task-categories/use-task-category-form'
import type { TaskCategoryDetail } from '@/features/task-categories/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const TASK_PRIORITY_FORM_ID = 'task-category-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface TaskCategoryFormBodyProps {
  onSuccess: (taskCategory: TaskCategoryDetail) => void
  onCancel: () => void
}

/**
 * The task category create form as a replica of the detail (wave 3 of the "no
 * edit pages" plan): the same rows, closed until clicked (Fatto/Ripristina,
 * nothing saved per row); Salva validates and creates everything at once.
 */
export function TaskCategoryFormBody({ onSuccess, onCancel }: TaskCategoryFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useTaskCategoryForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('taskCategories.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={TASK_PRIORITY_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('taskCategories.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Tags />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('taskCategories.form.save'),
          saving: t('taskCategories.form.saving'),
          cancel: t('taskCategories.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('taskCategories.form.sections.identity.title')} full>
            <RecordFieldList>
              <TaskCategoryRows inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
