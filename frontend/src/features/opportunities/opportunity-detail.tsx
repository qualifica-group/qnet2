import { useTranslation } from 'react-i18next'
import { History, MessagesSquare, Paperclip } from 'lucide-react'
import { RecordCanvas, RecordCard, RecordMeta } from '@/components/detail/record-panel'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { useAbilities } from '@/features/auth/use-abilities'
import { NotesSection } from '@/features/notes/notes-section'
import { OPPORTUNITY_ATTACHABLE_ALIAS } from '@/features/opportunities/api'
import {
  OpportunityDetailHeader,
  OpportunityDetailStats,
} from '@/features/opportunities/opportunity-detail-header'
import { OpportunityDetailSections } from '@/features/opportunities/opportunity-detail-sections'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { OpportunityDetailWithPermissions as OpportunityDetailData } from '@/features/opportunities/types'

const NOTES_TAB = 'notes'
const DOCUMENTS_TAB = 'documents'
const ACTIVITY_TAB = 'activity'

/** Compact trigger sizing, mirrors `RequestWorkCollaboration`'s tab strip. */
const TRIGGER_CLASS = 'px-2.5 py-1 text-xs'

interface OpportunityDetailViewProps {
  opportunity: OpportunityDetailData
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

interface OpportunityDetailCollaborationProps {
  opportunity: OpportunityDetailData
}

/**
 * The record's collaboration surface, mirroring `RequestWorkCollaboration`
 * (the user explicitly wants the same treatment here): one card, a compact
 * tab strip of Notes | Documents | Activity, each tab gated by its OWN
 * authorization source and absent entirely when unauthorized. Absent as a
 * whole when no tab is authorized for the actor.
 */
function OpportunityDetailCollaboration({ opportunity }: OpportunityDetailCollaborationProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()

  const canViewNotes = can('request-management.view')
  const canViewDocuments = opportunity.permissions.actions.view_documents
  const canViewActivity = opportunity.permissions.actions.view_activity

  if (!canViewNotes && !canViewDocuments && !canViewActivity) {
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
              {/*
               * The notes hang off the Opportunity record itself
               * (`RequestManagementNotable::modelClass()` resolves to
               * `Opportunity`), so `opportunity.id` is the same thread the
               * work panel reads. The server additionally requires
               * `request-management.viewAll` OR being that opportunity's GA2
               * operator (`RequestManagementNotable::authorizeRead`), which
               * the client cannot evaluate — `NotesSection` owns its own
               * error state for that residual case, same exposure the work
               * panel already has.
               */}
              <NotesSection
                entityType={REQUEST_MANAGEMENT_DOMAIN}
                entityId={opportunity.id}
                showHeader={false}
              />
            </TabsContent>
          ) : null}
          {canViewDocuments ? (
            <TabsContent value={DOCUMENTS_TAB}>
              <DocumentsSection
                resource={OPPORTUNITY_ATTACHABLE_ALIAS}
                id={opportunity.id}
                canUpload={can('attachments.create')}
                canDelete={can('attachments.delete')}
              />
            </TabsContent>
          ) : null}
          {canViewActivity ? (
            <TabsContent value={ACTIVITY_TAB}>
              <ActivityLogSection resource="opportunities" id={opportunity.id} />
            </TabsContent>
          ) : null}
        </div>
      </Tabs>
    </RecordCard>
  )
}

/**
 * Read-only detail of a single opportunity, rendered as an enterprise-CRM
 * record (spec 0040 AC-077): one identity/KPI/sections card, a collaboration
 * card (notes/documents/activity, spec 0049 AC-064's collected-information
 * lives in the sections card), and a metadata footer. Container-query driven
 * (`RecordCanvas`) so the same tree renders correctly both inside a
 * resizable Sheet and on the full-bleed `/opportunities/:id` page.
 */
export function OpportunityDetailView({ opportunity, onEdit }: OpportunityDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(opportunity.created_at)
  const updatedAt = formatDateTime(opportunity.updated_at)

  return (
    <RecordCanvas>
      <RecordCard>
        <OpportunityDetailHeader opportunity={opportunity} onEdit={onEdit} />
        <OpportunityDetailStats opportunity={opportunity} />
        <OpportunityDetailSections opportunity={opportunity} />
      </RecordCard>

      <OpportunityDetailCollaboration opportunity={opportunity} />

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('opportunities.columns.createdAt')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('opportunities.columns.updatedAt')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
