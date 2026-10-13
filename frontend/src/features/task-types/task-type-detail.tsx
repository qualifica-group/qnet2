import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Shapes } from 'lucide-react'
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
import { TaskTypeRows } from '@/features/task-types/task-type-rows'
import { useTaskTypeForm, type TaskTypeFormValues } from '@/features/task-types/use-task-type-form'
import type { TaskTypeDetailWithPermissions, TaskTypeFormMode } from '@/features/task-types/types'

interface TaskTypeDetailViewProps {
  taskType: TaskTypeDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single task type on the record kit: identity band, the
 * "Dettagli" rows, the activity in the side column, a metadata footer. There
 * is no edit page (wave 3 of the "no edit pages" plan): every row edits IN
 * PLACE, one at a time, each save a PATCH of that field alone.
 */
export function TaskTypeDetailView(props: TaskTypeDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.taskType.permissions}>
      <TaskTypeDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function TaskTypeDetailContent({ taskType, onChanged }: TaskTypeDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<TaskTypeFormMode>(() => ({ type: 'edit', taskType }), [taskType])
  const { form, onSubmit, serverError, clearServerError } = useTaskTypeForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<TaskTypeFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('taskTypes.form.genericError'),
  })
  const canEdit = taskType.permissions.resource.update
  const createdAt = formatDateTime(taskType.created_at)
  const updatedAt = formatDateTime(taskType.updated_at)
  const tabs: RecordCollaborationTab[] = taskType.permissions.actions.view_activity
    ? [activityLogTab('task-types', taskType.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={taskType.name} icon={<Shapes />} />}
            title={taskType.name}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('taskTypes.form.sections.identity.title')} full>
              <RecordFieldList>
                <TaskTypeRows inline={inline} canEdit={canEdit} />
                <RecordField label={t('taskTypes.detail.sort_order')}>{taskType.sort_order}</RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('taskTypes.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('taskTypes.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
