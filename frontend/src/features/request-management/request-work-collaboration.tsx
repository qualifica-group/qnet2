import { useTranslation } from 'react-i18next'
import { History, MessagesSquare, Paperclip } from 'lucide-react'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { useAbilities } from '@/features/auth/use-abilities'
import { NotesSection } from '@/features/notes/notes-section'
import { OPPORTUNITY_ATTACHABLE_ALIAS } from '@/features/opportunities/api'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'
import type { RequestWorkPanel } from '@/features/request-management/types'

const NOTES_TAB = 'notes'
const DOCUMENTS_TAB = 'documents'
const ACTIVITY_TAB = 'activity'

const TRIGGER_CLASS = 'px-2.5 py-1 text-xs'

interface RequestWorkCollaborationProps {
  panel: RequestWorkPanel
  /** From the panel's server-derived permission set (`view_activity` action). */
  canViewActivity: boolean
}

/**
 * The record's collaboration surface, collapsed into ONE card with a compact
 * tab strip: notes, documents and history compete for the same space instead
 * of stacking three full-height sections below the editable form. Each tab
 * mounts a self-contained feature section; gating stays here, mirroring the
 * table's row actions. Every section is keyed on `panel.opportunity_id`, not
 * `panel.id`: documents/notes/activity stay anchored to the Opportunity
 * (spec 0086 D-9), the same record the table's row actions target.
 */
export function RequestWorkCollaboration({ panel, canViewActivity }: RequestWorkCollaborationProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()

  const canViewDocuments = can('request-management.viewDocuments')

  return (
    <section className="min-w-0 rounded-xl border bg-card shadow-sm">
      {/* Notes is always available, so it is always the first tab. */}
      <Tabs defaultValue={NOTES_TAB} className="gap-0">
        <div className="px-4 py-3">
          <TabsList>
            <TabsTrigger value={NOTES_TAB} className={TRIGGER_CLASS}>
              <MessagesSquare className="size-3.5" aria-hidden="true" />
              {t('requestManagement.workPanel.collaboration.notesTab', { defaultValue: 'Notes' })}
            </TabsTrigger>
            {canViewDocuments && (
              <TabsTrigger value={DOCUMENTS_TAB} className={TRIGGER_CLASS}>
                <Paperclip className="size-3.5" aria-hidden="true" />
                {t('requestManagement.workPanel.collaboration.documentsTab', { defaultValue: 'Documents' })}
              </TabsTrigger>
            )}
            {canViewActivity && (
              <TabsTrigger value={ACTIVITY_TAB} className={TRIGGER_CLASS}>
                <History className="size-3.5" aria-hidden="true" />
                {t('requestManagement.workPanel.collaboration.activityTab', { defaultValue: 'History' })}
              </TabsTrigger>
            )}
          </TabsList>
        </div>
        <div className="border-t" />
        <div className="min-w-0 p-4">
          <TabsContent value={NOTES_TAB}>
            <NotesSection entityType={REQUEST_MANAGEMENT_DOMAIN} entityId={panel.opportunity_id} showHeader={false} />
          </TabsContent>
          {canViewDocuments && (
            <TabsContent value={DOCUMENTS_TAB}>
              <DocumentsSection
                resource={OPPORTUNITY_ATTACHABLE_ALIAS}
                id={panel.opportunity_id}
                canUpload={can('attachments.create')}
                canDelete={can('attachments.delete')}
              />
            </TabsContent>
          )}
          {canViewActivity && (
            <TabsContent value={ACTIVITY_TAB}>
              <ActivityLogSection resource={REQUEST_MANAGEMENT_DOMAIN} id={panel.opportunity_id} />
            </TabsContent>
          )}
        </div>
      </Tabs>
    </section>
  )
}
