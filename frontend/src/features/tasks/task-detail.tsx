import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCollaborationCard } from '@/components/detail/record-collaboration-card'
import { RecordCanvas, RecordCard, RecordMeta, RecordSectionsGrid } from '@/components/detail/record-panel'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { formatDateTime } from '@/features/table/cell-renderers'
import { TaskActionsBar } from '@/features/tasks/task-actions-bar'
import { useTaskCollaborationTabs } from '@/features/tasks/task-collaboration-section'
import { TaskDetailHeader, TaskDetailStats } from '@/features/tasks/task-detail-header'
import {
  TaskDetailClosureSection,
  TaskDetailLinksSection,
  TaskDetailRecurrenceSection,
} from '@/features/tasks/task-detail-link-sections'
import {
  TaskDetailClassificationSection,
  TaskDetailIdentitySection,
  TaskDetailPeopleSection,
  TaskDetailPlanningSection,
} from '@/features/tasks/task-detail-sections'
import { TaskSubtasksSection } from '@/features/tasks/task-subtasks-section'
import { useTaskInlineEdit } from '@/features/tasks/use-task-inline-edit'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'

interface TaskDetailViewProps {
  task: TaskDetailWithPermissions
  /** Opens a child task's own detail (AC-085). */
  onOpenSubtask: (subtaskId: number) => void
  /** Opens the create form with this task prefilled and locked as the parent (AC-085). */
  onCreateSubtask: () => void
  /** Called after an in-place save, so the host refreshes whatever lists the task (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single task, on the same `RecordCanvas` kit as the
 * Opportunita'/Commessa records: identity band (`TaskDetailHeader`), domain
 * actions, KPI strip, then the sections.
 *
 * Spec 0195 (user directive 2026-10-06): there is no edit page any more — the
 * sections' fields edit IN PLACE, one at a time (`TaskInlineField`, driven by
 * `useTaskInlineEdit`), each save a PATCH of that field alone.
 *
 * Spec 0117/0134 D-3: note, documenti, log attivita' e segnatempo vivono
 * nella card di collaborazione, nella colonna laterale (Opportunita' reference
 * layout), non piu' sotto la card principale.
 */
export function TaskDetailView(props: TaskDetailViewProps) {
  // The edit form reads the field permissions while it is built (`useTaskForm`),
  // so the provider wraps the whole detail, not just the sections.
  return (
    <ResourcePermissionsProvider permissions={props.task.permissions}>
      <TaskDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function TaskDetailContent({ task, onOpenSubtask, onCreateSubtask, onChanged }: TaskDetailViewProps) {
  const { t } = useTranslation()
  const collaborationTabs = useTaskCollaborationTabs(task)
  const editor = useTaskInlineEdit(task, onChanged)
  const createdAt = formatDateTime(task.created_at)
  const updatedAt = formatDateTime(task.updated_at)

  return (
    <RecordCanvas>
      <RecordBody
        side={collaborationTabs.length > 0 ? <RecordCollaborationCard tabs={collaborationTabs} /> : null}
      >
        <RecordCard>
          <TaskDetailHeader task={task} />

          <TaskActionsBar task={task} />

          <TaskDetailStats task={task} />

          <RecordSectionsGrid>
            {/* Provider only (no DOM): the inline editors share the edit form. */}
            <Form {...editor.form}>
              <TaskDetailIdentitySection task={task} editor={editor} />
              <TaskDetailClassificationSection task={task} editor={editor} />
              <TaskDetailPeopleSection task={task} editor={editor} />
              <TaskDetailPlanningSection task={task} editor={editor} />
              <TaskDetailLinksSection task={task} editor={editor} />
              <TaskDetailClosureSection task={task} editor={editor} />
              <TaskDetailRecurrenceSection task={task} editor={editor} />
            </Form>

            <TaskSubtasksSection
              parentTaskId={task.id}
              subtasks={task.subtasks}
              onOpen={onOpenSubtask}
              onCreate={onCreateSubtask}
              canCreateSubtask={task.permissions.actions.create_subtask}
              canReorder={task.permissions.resource.update}
            />
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('tasks.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('tasks.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
