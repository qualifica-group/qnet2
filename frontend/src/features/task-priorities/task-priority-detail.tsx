import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { SignalHigh } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import {
  RecordCanvas,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { formatDateTime } from '@/features/table/cell-renderers'
import { TaskPriorityRows } from '@/features/task-priorities/task-priority-rows'
import { useTaskPriorityForm, type TaskPriorityFormValues } from '@/features/task-priorities/use-task-priority-form'
import type { TaskPriorityDetailWithPermissions, TaskPriorityFormMode } from '@/features/task-priorities/types'

interface TaskPriorityDetailViewProps {
  taskPriority: TaskPriorityDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single task priority on the record kit: identity band, the
 * "Dettagli" rows, the activity in the side column, a metadata footer. There
 * is no edit page (wave 3 of the "no edit pages" plan): every row edits IN
 * PLACE, one at a time, each save a PATCH of that field alone.
 */
export function TaskPriorityDetailView(props: TaskPriorityDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.taskPriority.permissions}>
      <TaskPriorityDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function TaskPriorityDetailContent({ taskPriority, onChanged }: TaskPriorityDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<TaskPriorityFormMode>(() => ({ type: 'edit', taskPriority }), [taskPriority])
  const { form, onSubmit, serverError, clearServerError } = useTaskPriorityForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<TaskPriorityFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('taskPriorities.form.genericError'),
  })
  const canEdit = taskPriority.permissions.resource.update
  const createdAt = formatDateTime(taskPriority.created_at)
  const updatedAt = formatDateTime(taskPriority.updated_at)
  const tabs: RecordCollaborationTab[] = taskPriority.permissions.actions.view_activity
    ? [activityLogTab('task-priorities', taskPriority.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={taskPriority.name} icon={<SignalHigh />} />}
            title={taskPriority.name}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('taskPriorities.form.sections.identity.title')} full>
              <RecordFieldList>
                <TaskPriorityRows inline={inline} canEdit={canEdit} />
                <RecordField label={t('taskPriorities.detail.sort_order')}>{taskPriority.sort_order}</RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('taskPriorities.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('taskPriorities.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
