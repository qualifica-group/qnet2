import { useTranslation } from 'react-i18next'
import { ListChecks } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCanvas, RecordCardHeader, RecordMeta, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { formatDateTime } from '@/features/table/cell-renderers'
import { TaskTemplateItemsOverview } from '@/features/task-templates/task-template-items-overview'
import {
  TaskTemplateBoardEditor,
  TaskTemplateBoardSection,
  TaskTemplateIdentityRecordSection,
} from '@/features/task-templates/task-template-record-sections'
import {
  TASK_TEMPLATE_BOARD_FIELD,
  useTaskTemplateInlineEdit,
} from '@/features/task-templates/use-task-template-inline-edit'
import type { TaskTemplateDetailWithPermissions } from '@/features/task-templates/types'

interface TaskTemplateDetailViewProps {
  taskTemplate: TaskTemplateDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the template (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single task template on the record kit every detail uses:
 * identity band, the "Dettagli" rows and the "Fasi e attivita'" board, the
 * activity in the side column, a metadata footer.
 *
 * There is no edit page (spec 0227): every row edits IN PLACE, one at a time
 * (`useTaskTemplateInlineEdit`). The board is ONE full-width row whose pencil
 * swaps the read-only list for the very board the create form uses (D-3).
 */
export function TaskTemplateDetailView(props: TaskTemplateDetailViewProps) {
  // The rows read the field permissions (`items` gates the board), so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.taskTemplate.permissions}>
      <TaskTemplateDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function TaskTemplateDetailContent({ taskTemplate, onChanged }: TaskTemplateDetailViewProps) {
  const { t } = useTranslation()
  const { form, inline, board } = useTaskTemplateInlineEdit(taskTemplate, onChanged)
  const canEdit = taskTemplate.permissions.resource.update
  const createdAt = formatDateTime(taskTemplate.created_at)
  const updatedAt = formatDateTime(taskTemplate.updated_at)
  const collaborationTabs = taskTemplate.permissions.actions.view_activity
    ? [activityLogTab('task-templates', taskTemplate.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={collaborationTabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={taskTemplate.name} icon={<ListChecks />} />}
            title={taskTemplate.name}
            legacyId={taskTemplate.old_id}
            subtitle={t('taskTemplates.detail.itemsCount', { count: taskTemplate.items_count })}
          />
        }
      >
        {/* Provider only (no DOM): the inline editors share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <TaskTemplateIdentityRecordSection
              values={{ name: taskTemplate.name, description: taskTemplate.description, is_active: taskTemplate.is_active }}
              form={form}
              inline={inline}
              canEdit={canEdit}
            />
            <TaskTemplateBoardSection>
              <RecordInlineField
                field={TASK_TEMPLATE_BOARD_FIELD}
                label={t('taskTemplates.form.sections.stages.title')}
                layout="block"
                inline={inline}
                canEdit={canEdit}
                editor={<TaskTemplateBoardEditor board={board} disabled={inline.isSaving} />}
              >
                <TaskTemplateItemsOverview taskTemplate={taskTemplate} />
              </RecordInlineField>
            </TaskTemplateBoardSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      {createdAt || updatedAt ? (
        <RecordMeta>
          {createdAt ? (
            <span>
              <span className="font-medium">{t('taskTemplates.detail.created_at')}</span>{' '}
              <span aria-hidden="true">·</span> {createdAt}
            </span>
          ) : null}
          {updatedAt ? (
            <span>
              <span className="font-medium">{t('taskTemplates.detail.updated_at')}</span>{' '}
              <span aria-hidden="true">·</span> {updatedAt}
            </span>
          ) : null}
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
