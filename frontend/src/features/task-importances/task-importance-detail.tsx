import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Star } from 'lucide-react'
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
import { TaskImportanceRows } from '@/features/task-importances/task-importance-rows'
import { useTaskImportanceForm, type TaskImportanceFormValues } from '@/features/task-importances/use-task-importance-form'
import type { TaskImportanceDetailWithPermissions, TaskImportanceFormMode } from '@/features/task-importances/types'

interface TaskImportanceDetailViewProps {
  taskImportance: TaskImportanceDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single task importance on the record kit: identity band, the
 * "Dettagli" rows, the activity in the side column, a metadata footer. There
 * is no edit page (wave 3 of the "no edit pages" plan): every row edits IN
 * PLACE, one at a time, each save a PATCH of that field alone.
 */
export function TaskImportanceDetailView(props: TaskImportanceDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.taskImportance.permissions}>
      <TaskImportanceDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function TaskImportanceDetailContent({ taskImportance, onChanged }: TaskImportanceDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<TaskImportanceFormMode>(() => ({ type: 'edit', taskImportance }), [taskImportance])
  const { form, onSubmit, serverError, clearServerError } = useTaskImportanceForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<TaskImportanceFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('taskImportances.form.genericError'),
  })
  const canEdit = taskImportance.permissions.resource.update
  const createdAt = formatDateTime(taskImportance.created_at)
  const updatedAt = formatDateTime(taskImportance.updated_at)
  const tabs: RecordCollaborationTab[] = taskImportance.permissions.actions.view_activity
    ? [activityLogTab('task-importances', taskImportance.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={taskImportance.name} icon={<Star />} />}
            title={taskImportance.name}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('taskImportances.form.sections.identity.title')} full>
              <RecordFieldList>
                <TaskImportanceRows inline={inline} canEdit={canEdit} />
                <RecordField label={t('taskImportances.detail.sort_order')}>{taskImportance.sort_order}</RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('taskImportances.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('taskImportances.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
