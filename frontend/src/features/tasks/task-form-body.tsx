import { useTranslation } from 'react-i18next'
import { Paperclip } from 'lucide-react'
import { Form } from '@/components/ui/form'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCollaborationCard } from '@/components/detail/record-collaboration-card'
import { RecordCanvas, RecordCard } from '@/components/detail/record-panel'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { useTaskForm } from '@/features/tasks/use-task-form'
import { TaskAttachmentStaging } from '@/features/tasks/task-attachment-staging'
import { TaskCreateSections } from '@/features/tasks/task-create-sections'
import { TaskFormHeader } from '@/features/tasks/task-form-header'
import { duplicateSource } from '@/features/tasks/task-form-hydration'
import type { TaskCreateFormMode, TaskDetail } from '@/features/tasks/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const TASK_FORM_ID = 'task-form'

interface TaskFormBodyProps {
  mode: TaskCreateFormMode
  onSuccess: (task: TaskDetail) => void
  onCancel: () => void
}

/**
 * The task create/duplicate form UI. Every field is wrapped in `MetaField`
 * (spec 0004): hidden means absent, non-editable means disabled, `required`
 * comes from the resolved `ResourcePermissions`. The two fields the backend
 * prohibits — `creator_id` and `completion_percentage` (D-6/D-10) — have no
 * control at all.
 *
 * A replica of the task detail (spec 0195 D-8): the same `RecordCanvas`, the
 * record card with its identity band, KPI strip and sections (rows closed
 * until clicked, see `TaskCreateSections`), and the side card where the detail keeps its
 * documents — here the files staged for upload after the first save. There
 * is no edit form: the detail edits a persisted task in place.
 *
 * Pure composition: every non-render concern lives in `useTaskForm`.
 */
export function TaskFormBody({ mode, onSuccess, onCancel }: TaskFormBodyProps) {
  const { t } = useTranslation()
  const taskForm = useTaskForm({ mode, onSuccess })
  const {
    form,
    serverError,
    onSubmit,
    completionPercentage,
    stagedAttachments,
    addStagedAttachments,
    removeStagedAttachment,
  } = taskForm
  const draft = useDraftInlineEdit(form)
  // AC-085: opened as "crea sotto-task", the parent arrives prefilled and locked.
  const parentLocked = mode.type === 'create' && (mode.parentTaskId ?? null) !== null
  const { isSubmitting } = form.formState

  const attachmentsTab = {
    value: 'attachments',
    label: t('tasks.form.attachments.title'),
    icon: <Paperclip className="size-3.5" aria-hidden="true" />,
    content: (
      <TaskAttachmentStaging files={stagedAttachments} onAdd={addStagedAttachments} onRemove={removeStagedAttachment} />
    ),
  }

  return (
    <Form {...form}>
      {/* `display: contents`: this native `<form>` only scopes the HTML submit
          boundary, it must not become an extra box around the canvas. */}
      <form id={TASK_FORM_ID} onSubmit={form.handleSubmit(onSubmit)} className="contents" noValidate>
        <RecordCanvas>
          <RecordBody side={<RecordCollaborationCard tabs={[attachmentsTab]} />}>
            <RecordCard>
              <TaskFormHeader
                control={form.control}
                formId={TASK_FORM_ID}
                isSubmitting={isSubmitting}
                submitError={serverError}
                onCancel={onCancel}
                completionPercentage={completionPercentage}
              />
              <TaskCreateSections
                taskForm={taskForm}
                draft={draft}
                source={duplicateSource(mode)}
                parentLocked={parentLocked}
              />
            </RecordCard>

            {/* The same actions the identity band carries, repeated where the
                form ends: the operator finishes typing far from the top. */}
            <RecordFormActions
              formId={TASK_FORM_ID}
              isSubmitting={isSubmitting}
              submitLabel={t('tasks.form.save')}
              submittingLabel={t('tasks.form.saving')}
              cancel={{ label: t('tasks.form.cancel'), onCancel }}
            />
          </RecordBody>
        </RecordCanvas>
      </form>
    </Form>
  )
}
