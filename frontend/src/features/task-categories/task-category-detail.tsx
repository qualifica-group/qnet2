import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Tags } from 'lucide-react'
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
import { TaskCategoryRows } from '@/features/task-categories/task-category-rows'
import { useTaskCategoryForm, type TaskCategoryFormValues } from '@/features/task-categories/use-task-category-form'
import type { TaskCategoryDetailWithPermissions, TaskCategoryFormMode } from '@/features/task-categories/types'

interface TaskCategoryDetailViewProps {
  taskCategory: TaskCategoryDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single task category on the record kit: identity band, the
 * "Dettagli" rows, the activity in the side column, a metadata footer. There
 * is no edit page (wave 3 of the "no edit pages" plan): every row edits IN
 * PLACE, one at a time, each save a PATCH of that field alone.
 */
export function TaskCategoryDetailView(props: TaskCategoryDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.taskCategory.permissions}>
      <TaskCategoryDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function TaskCategoryDetailContent({ taskCategory, onChanged }: TaskCategoryDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<TaskCategoryFormMode>(() => ({ type: 'edit', taskCategory }), [taskCategory])
  const { form, onSubmit, serverError, clearServerError } = useTaskCategoryForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<TaskCategoryFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('taskCategories.form.genericError'),
  })
  const canEdit = taskCategory.permissions.resource.update
  const createdAt = formatDateTime(taskCategory.created_at)
  const updatedAt = formatDateTime(taskCategory.updated_at)
  const tabs: RecordCollaborationTab[] = taskCategory.permissions.actions.view_activity
    ? [activityLogTab('task-categories', taskCategory.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={taskCategory.name} icon={<Tags />} />}
            title={taskCategory.name}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('taskCategories.form.sections.identity.title')} full>
              <RecordFieldList>
                <TaskCategoryRows inline={inline} canEdit={canEdit} taskCategory={taskCategory} />
                <RecordField label={t('taskCategories.detail.sort_order')}>{taskCategory.sort_order}</RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('taskCategories.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('taskCategories.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
