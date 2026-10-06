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
import { OPPORTUNITY_ATTACHABLE_ALIAS } from '@/features/opportunities/api'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { NotesSection } from '@/features/notes/notes-section'
import { QuoteDetailHeader, QuoteDetailStats } from '@/features/quotes/quote-detail-header'
import { QuoteDetailLines } from '@/features/quotes/quote-detail-lines'
import { QuoteDetailSections } from '@/features/quotes/quote-detail-sections'
import { useQuoteInlineEdit } from '@/features/quotes/use-quote-inline-edit'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'
import { useRegistryDocumentsTab } from '@/features/registries/use-registry-documents-tab'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { QuoteDetailWithPermissions } from '@/features/quotes/types'

interface QuoteDetailViewProps {
  quote: QuoteDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the offer (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * The offer's collaboration tabs: Note | Documenti opportunità | Attività
 * (Opportunita' reference layout).
 *
 * The notes thread stays the parent Opportunity's (spec 0085 D-1) — the note is
 * SCOPED to this offer via `lockedQuoteId`, never moved onto another entity —
 * so no selector is offered here: the context is given. Unlike every other
 * record's Notes tab, it is unconditional (not gated behind an ability): the
 * offer detail has always shown it regardless of `request-management.view`.
 *
 * The documents are the parent Opportunity's, mounted READ-ONLY exactly as the
 * Contract detail mounts them (spec 0072 AC-047, user directive 2026-08-31
 * "quelli che trovo in contratti"): an offer never owns an attachment, so it
 * must not offer to add or remove one — also unconditional. The anagrafica's
 * documents (spec 0173) follow, read-only too, gated on
 * `registries.viewDocuments`. Attività is gated on its own action flag and
 * absent entirely when unauthorized.
 */
function useCollaborationTabs(quote: QuoteDetailWithPermissions): RecordCollaborationTab[] {
  const { t } = useTranslation()
  const registryDocumentsTab = useRegistryDocumentsTab(quote.registry?.id)
  const tabs: RecordCollaborationTab[] = [
    {
      value: 'notes',
      label: t('notes.section.title'),
      icon: <MessagesSquare className="size-3.5" aria-hidden="true" />,
      content: (
        <NotesSection
          entityType={REQUEST_MANAGEMENT_DOMAIN}
          entityId={quote.opportunity_id}
          showHeader={false}
          lockedQuoteId={quote.id}
        />
      ),
    },
    {
      value: 'opportunity-documents',
      label: t('quotes.detail.tabs.opportunityDocuments'),
      icon: <Paperclip className="size-3.5" aria-hidden="true" />,
      content: (
        <DocumentsSection
          resource={OPPORTUNITY_ATTACHABLE_ALIAS}
          id={quote.opportunity_id}
          canUpload={false}
          canDelete={false}
        />
      ),
    },
  ]

  if (registryDocumentsTab) {
    tabs.push(registryDocumentsTab)
  }

  if (quote.permissions.actions.view_activity) {
    tabs.push(activityLogTab('quotes', quote.id, t('activityLog.title')))
  }

  return tabs
}

/**
 * Detail of a single offer (spec 0065), rendered as an enterprise-CRM record
 * on the same `RecordCanvas` kit as `/opportunities/:id`: on the left ONE card
 * carrying identity + KPI strip + titled sections and, as its closing band,
 * the offer/cost rows with the persisted summary; the collaboration card
 * (note, documenti, attivita') on the right; a metadata footer below.
 * Container-query driven, so the same tree renders correctly both inside a
 * resizable Sheet and on the full-bleed page.
 *
 * There is no edit page (spec 0197, the Commesse model): the sections' fields
 * and the two line sets edit IN PLACE, one at a time (`RecordInlineField`,
 * driven by `useQuoteInlineEdit`), each save a PATCH of that field alone.
 */
export function QuoteDetailView(props: QuoteDetailViewProps) {
  // The edit form reads the field permissions while it is built, so the
  // provider wraps the whole detail, not just the sections.
  return (
    <ResourcePermissionsProvider permissions={props.quote.permissions}>
      <QuoteDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function QuoteDetailContent({ quote, onChanged }: QuoteDetailViewProps) {
  const { t } = useTranslation()
  const editor = useQuoteInlineEdit(quote, onChanged)
  const collaborationTabs = useCollaborationTabs(quote)
  const createdAt = formatDateTime(quote.created_at)
  const updatedAt = formatDateTime(quote.updated_at)

  return (
    <RecordCanvas>
      <RecordBody
        side={collaborationTabs.length > 0 ? <RecordCollaborationCard tabs={collaborationTabs} /> : null}
      >
        <RecordCard>
          <QuoteDetailHeader quote={quote} />
          <QuoteDetailStats quote={quote} />
          {/* Provider only (no DOM): the inline editors share the edit form. */}
          <Form {...editor.form}>
            <QuoteDetailSections quote={quote} editor={editor} />
            <QuoteDetailLines quote={quote} editor={editor} />
          </Form>
        </RecordCard>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('quotes.detail.createdAt')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('quotes.detail.updatedAt')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
