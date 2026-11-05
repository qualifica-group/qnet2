import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { TFunction } from 'i18next'
import { actionErrorMessage } from '@/features/tasks/task-action-error-message'
import { useRequestTaskUpdate } from '@/features/tasks/use-task-mutations'
import {
  RequestUpdateDialog,
  type RequestUpdateOption,
  type RequestUpdateTexts,
} from '@/features/shared/request-update-dialog'
import type { TaskDetailWithPermissions, TaskNamedRef, TaskRequestUpdateTarget } from '@/features/tasks/types'

const FORM_ID = 'task-request-update-form'
const DEFAULT_TARGET: TaskRequestUpdateTarget = 'assignees'

/** Count of distinct recipients across both lists (D-14 `all`), a user in both counted once. */
function uniqueRecipientCount(assignees: TaskNamedRef[], watchers: TaskNamedRef[]): number {
  const ids = new Set([...assignees, ...watchers].map((ref) => ref.id))
  return ids.size
}

function buildTargetOptions(task: TaskDetailWithPermissions, t: TFunction): RequestUpdateOption<TaskRequestUpdateTarget>[] {
  return [
    {
      value: 'assignees',
      label: t('tasks.actions.requestUpdate.targetAssignees'),
      count: task.assignees.length,
      hint: task.watchers.length > 0 ? t('tasks.actions.requestUpdate.targetAssigneesHint') : undefined,
    },
    {
      value: 'observers',
      label: t('tasks.actions.requestUpdate.targetObservers'),
      count: task.watchers.length,
    },
    {
      value: 'all',
      label: t('tasks.actions.requestUpdate.targetAll'),
      count: uniqueRecipientCount(task.assignees, task.watchers),
    },
  ]
}

function buildTexts(t: TFunction): RequestUpdateTexts {
  return {
    title: t('tasks.actions.requestUpdate.title'),
    description: t('tasks.actions.requestUpdate.description'),
    target: t('tasks.actions.requestUpdate.target'),
    recipientsEmpty: t('tasks.actions.requestUpdate.recipientsEmpty'),
    message: t('tasks.actions.requestUpdate.message'),
    messagePlaceholder: t('tasks.actions.requestUpdate.messagePlaceholder'),
    messageTooShort: t('tasks.actions.requestUpdate.messageTooShort'),
    messageTooLong: t('tasks.actions.requestUpdate.messageTooLong'),
    submit: t('tasks.actions.requestUpdate.submit'),
    success: t('tasks.actions.requestUpdate.success'),
  }
}

interface TaskRequestUpdateDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  task: TaskDetailWithPermissions
}

/**
 * "Richiedi aggiornamento" of a task (spec 0153 D-14): the groups are computed
 * from this task's own `assignees`/`watchers`; `assignees` also CCs every
 * watcher server-side. The form lives in the shared `RequestUpdateDialog`.
 */
export function TaskRequestUpdateDialog({ open, onOpenChange, task }: TaskRequestUpdateDialogProps) {
  const { t } = useTranslation()
  const requestUpdateMutation = useRequestTaskUpdate({ taskId: task.id })

  return (
    <RequestUpdateDialog
      open={open}
      onOpenChange={onOpenChange}
      formId={FORM_ID}
      options={buildTargetOptions(task, t)}
      defaultTarget={DEFAULT_TARGET}
      texts={buildTexts(t)}
      isPending={requestUpdateMutation.isPending}
      onSubmit={(payload) => requestUpdateMutation.mutateAsync(payload)}
      onError={(error) => toast.error(actionErrorMessage(t, error))}
    />
  )
}
