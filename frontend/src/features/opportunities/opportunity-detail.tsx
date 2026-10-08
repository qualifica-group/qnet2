import { useTranslation } from 'react-i18next'
import { MessagesSquare, Paperclip } from 'lucide-react'
import { Form } from '@/components/ui/form'
import { RecordCanvas, RecordCard, RecordMeta } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import {
  RecordCollaborationCard,
  type RecordCollaborationTab,
} from '@/components/detail/record-collaboration-card'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { useAbilities } from '@/features/auth/use-abilities'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { NotesSection } from '@/features/notes/notes-section'
import { OPPORTUNITY_ATTACHABLE_ALIAS } from '@/features/opportunities/api'
import {
  OpportunityDetailHeader,
  OpportunityDetailStats,
} from '@/features/opportunities/opportunity-detail-header'
import { OpportunityDetailSections } from '@/features/opportunities/opportunity-detail-sections'
import { OpportunityQuotesSection } from '@/features/opportunities/opportunity-quotes-section'
import { useOpportunityInlineEdit } from '@/features/opportunities/use-opportunity-inline-edit'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'
import { useRegistryDocumentsTab } from '@/features/registries/use-registry-documents-tab'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { OpportunityDetailWithPermissions as OpportunityDetailData } from '@/features/opportunities/types'

const NOTES_TAB = 'notes'
const DOCUMENTS_TAB = 'documents'

interface OpportunityDetailViewProps {
  opportunity: OpportunityDetailData
  /** Called after an in-place save, so the host refreshes whatever lists the opportunity (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * The record's collaboration tabs (Notes | Documents | Registry documents |
 * Activity, spec 0173 for the read-only registry one), each gated
 * by its OWN authorization source and absent entirely when unauthorized.
 */
function useCollaborationTabs(opportunity: OpportunityDetailData): RecordCollaborationTab[] {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const registryDocumentsTab = useRegistryDocumentsTab(opportunity.registry_id)
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

  if (registryDocumentsTab) {
    tabs.push(registryDocumentsTab)
  }

  if (opportunity.permissions.actions.view_activity) {
    tabs.push(activityLogTab('opportunities', opportunity.id, t('activityLog.title')))
  }

  return tabs
}

/**
 * Detail of a single opportunity, rendered as an enterprise-CRM record (spec
 * 0040 AC-077): the identity/KPI/sections card on the left, the
 * collaboration card (notes/documents/activity) on the right, the Offerte
 * panel full width below both, and a metadata footer. Container-query driven
 * (`RecordCanvas`) so the same tree renders correctly both inside a
 * resizable Sheet and on the full-bleed `/opportunities/:id` page.
 *
 * There is no edit page (spec 0198): the sections' fields edit IN PLACE, one
 * at a time (`RecordInlineField`, driven by `useOpportunityInlineEdit`), each
 * save a PATCH of that field alone.
 */
export function OpportunityDetailView(props: OpportunityDetailViewProps) {
  // The edit form reads the field permissions while it is built, so the
  // provider wraps the whole detail, not just the sections.
  return (
    <ResourcePermissionsProvider permissions={props.opportunity.permissions}>
      <OpportunityDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function OpportunityDetailContent({ opportunity, onChanged }: OpportunityDetailViewProps) {
  const { t } = useTranslation()
  const editor = useOpportunityInlineEdit(opportunity, onChanged)
  const collaborationTabs = useCollaborationTabs(opportunity)
  const createdAt = formatDateTime(opportunity.created_at)
  const updatedAt = formatDateTime(opportunity.updated_at)

  return (
    <RecordCanvas>
      <RecordBody
        side={collaborationTabs.length > 0 ? <RecordCollaborationCard tabs={collaborationTabs} /> : null}
      >
        <RecordCard>
          <OpportunityDetailHeader opportunity={opportunity} />
          <OpportunityDetailStats opportunity={opportunity} />
          {/* Provider only (no DOM): the inline editors share the edit form. */}
          <Form {...editor.form}>
            <OpportunityDetailSections opportunity={opportunity} editor={editor} />
          </Form>
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
