import { useTranslation } from 'react-i18next'
import { Clock, MessagesSquare, Paperclip } from 'lucide-react'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { Badge } from '@/components/ui/badge'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { useAbilities } from '@/features/auth/use-abilities'
import { NotesSection } from '@/features/notes/notes-section'
import { TASK_ATTACHABLE_ALIAS, TASKS_DOMAIN } from '@/features/tasks/api'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'
import { TaskTimeEntriesSection } from '@/features/time-entries/task/task-time-entries-section'
import { useTaskTimeEntries } from '@/features/time-entries/task/use-task-time-entries'
import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'

const TIME_ENTRIES_TAB = 'time-entries'

/**
 * The task's collaboration tabs (Note | Documenti | Attivita' | Segnatempo,
 * spec 0117 D-11), each gated by its OWN authorization source and absent
 * entirely when unauthorized — rendered by the caller in the shared
 * `RecordCollaborationCard` (Opportunita' reference layout).
 *
 * Notes are gated on the resource permission alone: the membership half of
 * the server rule — the `TaskVisibilityScope` applied by
 * `TaskNotable::authorizeRead` — is not evaluable client-side, and
 * `NotesSection` owns its own error state for that residual case.
 *
 * Segnatempo (spec 0122 MT-F6/D-9) is the one tab whose data is fetched HERE
 * rather than lazily inside its own tab content: the trigger's total-minutes
 * badge needs it even while the tab itself is not the active one.
 */
export function useTaskCollaborationTabs(task: TaskDetailWithPermissions): RecordCollaborationTab[] {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const canViewTimeEntries = can('time-entries.viewAny')
  const timeEntriesQuery = useTaskTimeEntries(task.id, canViewTimeEntries)
  const tabs: RecordCollaborationTab[] = []

  if (can('tasks.view')) {
    tabs.push({
      value: 'notes',
      label: t('notes.section.title'),
      icon: <MessagesSquare className="size-3.5" aria-hidden="true" />,
      content: <NotesSection entityType={TASKS_DOMAIN} entityId={task.id} showHeader={false} />,
    })
  }

  if (task.permissions.actions.view_documents) {
    tabs.push({
      value: 'documents',
      label: t('attachments.title'),
      icon: <Paperclip className="size-3.5" aria-hidden="true" />,
      content: (
        <DocumentsSection
          resource={TASK_ATTACHABLE_ALIAS}
          id={task.id}
          canUpload={can('attachments.create')}
          canDelete={can('attachments.delete')}
        />
      ),
    })
  }

  if (task.permissions.actions.view_activity) {
    tabs.push(activityLogTab(TASKS_DOMAIN, task.id, t('activityLog.title')))
  }

  if (canViewTimeEntries) {
    tabs.push({
      value: TIME_ENTRIES_TAB,
      label: (
        <>
          {t('timeEntries.task.sectionTitle')}
          {timeEntriesQuery.data ? (
            <Badge variant="secondary" className="px-1.5 py-0 text-[10px]">
              {formatMinutesLabel(timeEntriesQuery.data.total_minutes)}
            </Badge>
          ) : null}
        </>
      ),
      icon: <Clock className="size-3.5" aria-hidden="true" />,
      content: <TaskTimeEntriesSection taskId={task.id} />,
    })
  }

  return tabs
}
