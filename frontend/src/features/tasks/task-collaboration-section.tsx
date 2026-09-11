import { useTranslation } from 'react-i18next'
import { History, MessagesSquare, Paperclip } from 'lucide-react'
import { RecordCard } from '@/components/detail/record-panel'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { useAbilities } from '@/features/auth/use-abilities'
import { NotesSection } from '@/features/notes/notes-section'
import { TASK_ATTACHABLE_ALIAS, TASKS_DOMAIN } from '@/features/tasks/api'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'

const NOTES_TAB = 'notes'
const DOCUMENTS_TAB = 'documents'
const ACTIVITY_TAB = 'activity'

/** Compact trigger sizing, identical to the Opportunita'/Contratto tab strip. */
const TRIGGER_CLASS = 'px-2.5 py-1 text-xs'

interface TaskCollaborationSectionProps {
  task: TaskDetailWithPermissions
}

interface CollaborationGates {
  canViewNotes: boolean
  canViewDocuments: boolean
  canViewActivity: boolean
  hasAny: boolean
}

/**
 * Per-tab authorization, each from its OWN source (spec 0117 D-11).
 *
 * Notes are gated on the resource permission alone because the second half
 * of the server's rule — the membership scope of `TaskVisibilityScope`,
 * applied by `TaskNotable::authorizeRead` — is not something the client can
 * evaluate. `NotesSection` owns its own error state for that residual case,
 * the same exposure the Opportunita' detail already has.
 */
function useCollaborationGates(task: TaskDetailWithPermissions): CollaborationGates {
  const { can } = useAbilities()

  const canViewNotes = can('tasks.view')
  const canViewDocuments = task.permissions.actions.view_documents
  const canViewActivity = task.permissions.actions.view_activity

  return {
    canViewNotes,
    canViewDocuments,
    canViewActivity,
    hasAny: canViewNotes || canViewDocuments || canViewActivity,
  }
}

/**
 * The task's collaboration surface: one card, a compact tab strip of Note |
 * Documenti | Attivita', each tab absent entirely when unauthorized, the
 * whole card absent when none of the three is.
 *
 * The activity log lives HERE and nowhere else since spec 0117: it used to
 * be a section of its own at the bottom of the detail card, and keeping both
 * would have shown the same log twice.
 */
export function TaskCollaborationSection({ task }: TaskCollaborationSectionProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const { canViewNotes, canViewDocuments, canViewActivity, hasAny } = useCollaborationGates(task)

  if (!hasAny) {
    return null
  }

  const defaultTab = canViewNotes ? NOTES_TAB : canViewDocuments ? DOCUMENTS_TAB : ACTIVITY_TAB

  return (
    <RecordCard>
      <Tabs defaultValue={defaultTab} className="gap-0">
        <div className="px-4 py-3">
          <TabsList>
            {canViewNotes ? (
              <TabsTrigger value={NOTES_TAB} className={TRIGGER_CLASS}>
                <MessagesSquare className="size-3.5" aria-hidden="true" />
                {t('notes.section.title')}
              </TabsTrigger>
            ) : null}
            {canViewDocuments ? (
              <TabsTrigger value={DOCUMENTS_TAB} className={TRIGGER_CLASS}>
                <Paperclip className="size-3.5" aria-hidden="true" />
                {t('attachments.title')}
              </TabsTrigger>
            ) : null}
            {canViewActivity ? (
              <TabsTrigger value={ACTIVITY_TAB} className={TRIGGER_CLASS}>
                <History className="size-3.5" aria-hidden="true" />
                {t('activityLog.title')}
              </TabsTrigger>
            ) : null}
          </TabsList>
        </div>
        <div className="border-t" />
        <div className="min-w-0 p-4">
          {canViewNotes ? (
            <TabsContent value={NOTES_TAB}>
              <NotesSection entityType={TASKS_DOMAIN} entityId={task.id} showHeader={false} />
            </TabsContent>
          ) : null}
          {canViewDocuments ? (
            <TabsContent value={DOCUMENTS_TAB}>
              <DocumentsSection
                resource={TASK_ATTACHABLE_ALIAS}
                id={task.id}
                canUpload={can('attachments.create')}
                canDelete={can('attachments.delete')}
              />
            </TabsContent>
          ) : null}
          {canViewActivity ? (
            <TabsContent value={ACTIVITY_TAB}>
              <ActivityLogSection resource={TASKS_DOMAIN} id={task.id} />
            </TabsContent>
          ) : null}
        </div>
      </Tabs>
    </RecordCard>
  )
}
