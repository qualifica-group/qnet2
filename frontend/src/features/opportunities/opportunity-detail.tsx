import { useTranslation } from 'react-i18next'
import { MessagesSquare, Paperclip } from 'lucide-react'
import { RecordCanvas, RecordCard, RecordMeta } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import {
  RecordCollaborationCard,
  type RecordCollaborationTab,
} from '@/components/detail/record-collaboration-card'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { useAbilities } from '@/features/auth/use-abilities'
import { NotesSection } from '@/features/notes/notes-section'
import { OPPORTUNITY_ATTACHABLE_ALIAS } from '@/features/opportunities/api'
import {
  OpportunityDetailHeader,
  OpportunityDetailStats,
} from '@/features/opportunities/opportunity-detail-header'
import { OpportunityDetailSections } from '@/features/opportunities/opportunity-detail-sections'
import { OpportunityQuotesSection } from '@/features/opportunities/opportunity-quotes-section'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { OpportunityDetailWithPermissions as OpportunityDetailData } from '@/features/opportunities/types'

const NOTES_TAB = 'notes'
const DOCUMENTS_TAB = 'documents'

interface OpportunityDetailViewProps {
  opportunity: OpportunityDetailData
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * The record's collaboration tabs (Notes | Documents | Activity), each gated
 * by its OWN authorization source and absent entirely when unauthorized.
 */
function useCollaborationTabs(opportunity: OpportunityDetailData): RecordCollaborationTab[] {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const tabs: RecordCollaborationTab[] = []

  if (can('request-management.view')) {
    tabs.push({
      value: NOTES_TAB,
      label: t('notes.section.title'),
      icon: <MessagesSquare className="size-3.5" aria-hidden="true" />,
      /*
       * The notes hang off the Opportunity record itself
       * (`RequestManagementNotable::modelClass()` resolves to `Opportunity`),
       * so `opportunity.id` is the same thread the work panel reads. The
       * server additionally requires `request-management.viewAll` OR being
       * that opportunity's GA2 operator (`RequestManagementNotable::authorizeRead`),
       * which the client cannot evaluate — `NotesSection` owns its own error
       * state for that residual case, same exposure the work panel has.
       */
      content: (
        <NotesSection
          entityType={REQUEST_MANAGEMENT_DOMAIN}
          entityId={opportunity.id}
          showHeader={false}
        />
      ),
    })
  }

  if (opportunity.permissions.actions.view_documents) {
    tabs.push({
      value: DOCUMENTS_TAB,
      label: t('attachments.title'),
      icon: <Paperclip className="size-3.5" aria-hidden="true" />,
      content: (
        <DocumentsSection
          resource={OPPORTUNITY_ATTACHABLE_ALIAS}
          id={opportunity.id}
          canUpload={can('attachments.create')}
          canDelete={can('attachments.delete')}
        />
      ),
    })
  }

  if (opportunity.permissions.actions.view_activity) {
    tabs.push(activityLogTab('opportunities', opportunity.id, t('activityLog.title')))
  }

  return tabs
}

/**
 * Read-only detail of a single opportunity, rendered as an enterprise-CRM
 * record (spec 0040 AC-077): the identity/KPI/sections card on the left, the
 * collaboration card (notes/documents/activity, spec 0049 AC-064's
 * collected-information lives in the sections card) on the right, the Offerte
 * panel full width below both, and a metadata footer. Container-query driven
 * (`RecordCanvas`) so the same tree renders correctly both inside a
 * resizable Sheet and on the full-bleed `/opportunities/:id` page.
 */
export function OpportunityDetailView({ opportunity, onEdit }: OpportunityDetailViewProps) {
  const { t } = useTranslation()
  const collaborationTabs = useCollaborationTabs(opportunity)
  const createdAt = formatDateTime(opportunity.created_at)
  const updatedAt = formatDateTime(opportunity.updated_at)

  return (
    <RecordCanvas>
      <RecordBody
        side={collaborationTabs.length > 0 ? <RecordCollaborationCard tabs={collaborationTabs} /> : null}
      >
        <RecordCard>
          <OpportunityDetailHeader opportunity={opportunity} onEdit={onEdit} />
          <OpportunityDetailStats opportunity={opportunity} />
          <OpportunityDetailSections opportunity={opportunity} />
        </RecordCard>
      </RecordBody>

      {/*
       * Keyed by id (spec 0067 D-2): the detail route/Sheet does not remount
       * on an opportunity change (only its params do), so the panel's own
       * empty-state/counter seed (`use-opportunity-quotes-panel.ts`) needs a
       * fresh mount to re-derive from the new opportunity's `quotes_count`.
       */}
      <OpportunityQuotesSection key={opportunity.id} opportunity={opportunity} />

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
