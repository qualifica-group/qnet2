import { useTranslation } from 'react-i18next'
import { History, MessagesSquare, Paperclip } from 'lucide-react'
import { RecordTabIcon } from '@/components/detail/record-tab-icon'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { DocumentsTabCount } from '@/features/attachments/documents-tab-count'
import { useAbilities } from '@/features/auth/use-abilities'
import { NotesSection } from '@/features/notes/notes-section'
import { NotesTabCount } from '@/features/notes/notes-tab-count'
import { OPPORTUNITY_ATTACHABLE_ALIAS } from '@/features/opportunities/api'
import { QUOTE_ATTACHABLE_ALIAS } from '@/features/quotes/api'
import { useRequestModule } from '@/features/request-management/request-module'
import type { RequestWorkPanel } from '@/features/request-management/types'
import { useInvalidateRequestDashboard } from '@/features/request-management/use-request-dashboard'

const NOTES_TAB = 'notes'
const DOCUMENTS_TAB = 'documents'
const OPPORTUNITY_DOCUMENTS_TAB = 'opportunity-documents'
const ACTIVITY_TAB = 'activity'


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
 * table's row actions. Notes and activity are keyed on `panel.opportunity_id`
 * (spec 0086 D-9), the same record the table's row actions target. The
 * documents are the Offerta's own (`panel.id`, spec 0415 D-3); the files
 * already on the Opportunity stay there and are shown READ-ONLY in their own
 * tab, like the Offer detail does.
 *
 * Le note aggiungono a quell'ancoraggio il filtro sull'Offerta
 * (`lockedQuoteId={panel.id}`, direttiva utente 2026-08-07): il thread resta
 * quello dell'Opportunita', ma la lista mostra solo le note di QUESTA Offerta
 * e il composer le crea con quel `quote_id` — identico al dettaglio Offerta
 * (`quote-detail.tsx`, spec 0085 D-1), niente selettore di scope.
 */
export function RequestWorkCollaboration({ panel, canViewActivity }: RequestWorkCollaborationProps) {
  const { t } = useTranslation()
  const module = useRequestModule()
  const { can } = useAbilities()
  const invalidateDashboard = useInvalidateRequestDashboard()

  const canViewDocuments = can(module.permission('viewDocuments'))

  return (
    <section className="min-w-0 rounded-xl border bg-card shadow-sm">
      {/* Notes is always available, so it is always the first tab. */}
      <Tabs defaultValue={NOTES_TAB} className="gap-0">
        <div className="px-4 py-3">
          <TabsList>
            <TabsTrigger value={NOTES_TAB}>
              <RecordTabIcon
                icon={<MessagesSquare className="size-3.5" aria-hidden="true" />}
                badge={<NotesTabCount entityType={module.key} entityId={panel.opportunity_id} quoteScope={panel.id} />}
              />
              {t('requestManagement.workPanel.collaboration.notesTab', { defaultValue: 'Notes' })}
            </TabsTrigger>
            {canViewDocuments && (
              <TabsTrigger value={DOCUMENTS_TAB}>
                <RecordTabIcon
                  icon={<Paperclip className="size-3.5" aria-hidden="true" />}
                  badge={<DocumentsTabCount resource={QUOTE_ATTACHABLE_ALIAS} id={panel.id} />}
                />
                {t('requestManagement.workPanel.collaboration.documentsTab', { defaultValue: 'Documents' })}
              </TabsTrigger>
            )}
            {canViewDocuments && (
              <TabsTrigger value={OPPORTUNITY_DOCUMENTS_TAB}>
                <RecordTabIcon
                  icon={<Paperclip className="size-3.5" aria-hidden="true" />}
                  badge={<DocumentsTabCount resource={OPPORTUNITY_ATTACHABLE_ALIAS} id={panel.opportunity_id} />}
                />
                {t('requestManagement.workPanel.collaboration.opportunityDocumentsTab')}
              </TabsTrigger>
            )}
            {canViewActivity && (
              <TabsTrigger value={ACTIVITY_TAB}>
                <History className="size-3.5" aria-hidden="true" />
                {t('requestManagement.workPanel.collaboration.activityTab', { defaultValue: 'History' })}
              </TabsTrigger>
            )}
          </TabsList>
        </div>
        <div className="border-t" />
        <div className="min-w-0 p-4">
          <TabsContent value={NOTES_TAB}>
            <NotesSection
              entityType={module.key}
              entityId={panel.opportunity_id}
              showHeader={false}
              lockedQuoteId={panel.id}
              onThreadChanged={invalidateDashboard}
            />
          </TabsContent>
          {canViewDocuments && (
            <TabsContent value={DOCUMENTS_TAB}>
              <DocumentsSection
                resource={QUOTE_ATTACHABLE_ALIAS}
                id={panel.id}
                canUpload={can('attachments.create')}
                canDelete={can('attachments.delete')}
              />
            </TabsContent>
          )}
          {canViewDocuments && (
            <TabsContent value={OPPORTUNITY_DOCUMENTS_TAB}>
              <DocumentsSection
                resource={OPPORTUNITY_ATTACHABLE_ALIAS}
                id={panel.opportunity_id}
                canUpload={false}
                canDelete={false}
              />
            </TabsContent>
          )}
          {canViewActivity && (
            <TabsContent value={ACTIVITY_TAB}>
              <ActivityLogSection resource={module.key} id={panel.opportunity_id} />
            </TabsContent>
          )}
        </div>
      </Tabs>
    </section>
  )
}
