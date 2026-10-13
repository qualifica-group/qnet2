import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Flag } from 'lucide-react'
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
import { TaskStatusRows } from '@/features/task-statuses/task-status-rows'
import { useTaskStatusForm, type TaskStatusFormValues } from '@/features/task-statuses/use-task-status-form'
import type { TaskStatusDetailWithPermissions, TaskStatusFormMode } from '@/features/task-statuses/types'

interface TaskStatusDetailViewProps {
  taskStatus: TaskStatusDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single task status on the record kit: identity band, the
 * "Dettagli" rows, the activity in the side column, a metadata footer. There
 * is no edit page (wave 3 of the "no edit pages" plan): every row edits IN
 * PLACE, one at a time, each save a PATCH of that field alone.
 */
export function TaskStatusDetailView(props: TaskStatusDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.taskStatus.permissions}>
      <TaskStatusDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function TaskStatusDetailContent({ taskStatus, onChanged }: TaskStatusDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<TaskStatusFormMode>(() => ({ type: 'edit', taskStatus }), [taskStatus])
  const { form, onSubmit, serverError, clearServerError } = useTaskStatusForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<TaskStatusFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('taskStatuses.form.genericError'),
  })
  const canEdit = taskStatus.permissions.resource.update
  const createdAt = formatDateTime(taskStatus.created_at)
  const updatedAt = formatDateTime(taskStatus.updated_at)
  const tabs: RecordCollaborationTab[] = taskStatus.permissions.actions.view_activity
    ? [activityLogTab('task-statuses', taskStatus.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={taskStatus.name} icon={<Flag />} />}
            title={taskStatus.name}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('taskStatuses.form.sections.identity.title')} full>
              <RecordFieldList>
                <TaskStatusRows inline={inline} canEdit={canEdit} isSystemRow={taskStatus.system_key !== null} />
                <RecordField label={t('taskStatuses.detail.sort_order')}>{taskStatus.sort_order}</RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('taskStatuses.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('taskStatuses.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
