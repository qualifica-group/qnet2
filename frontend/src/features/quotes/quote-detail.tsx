import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { History, MessagesSquare, Paperclip, TrendingDown, TrendingUp } from 'lucide-react'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { FormTabStrip, FORM_TAB_TRIGGER_CLASS } from '@/components/form-tab-strip'
import { RecordCanvas, RecordCard, RecordMeta } from '@/components/detail/record-panel'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { OPPORTUNITY_ATTACHABLE_ALIAS } from '@/features/opportunities/api'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { NotesSection } from '@/features/notes/notes-section'
import { QuoteDetailHeader, QuoteDetailStats } from '@/features/quotes/quote-detail-header'
import { QuoteDetailSections } from '@/features/quotes/quote-detail-sections'
import { QuoteLinesReadOnlyList } from '@/features/quotes/quote-lines-read-only'
import { QuoteSummary, totalsFromPersistedSummary } from '@/features/quotes/quote-summary'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { QuoteDetailWithPermissions } from '@/features/quotes/types'

const OFFER_TAB = 'offer'
const COSTS_TAB = 'costs'
const NOTES_TAB = 'notes'
const OPPORTUNITY_DOCUMENTS_TAB = 'opportunity-documents'
const ACTIVITY_TAB = 'activity'

/** Compact trigger sizing of the collaboration strip, mirrors `OpportunityDetailView`'s. */
const TRIGGER_CLASS = 'px-2.5 py-1 text-xs'

/**
 * Two-column body, same rule the Opportunity record follows: the record itself
 * on the left, the collaboration surface on the right, stacked in that order
 * while narrow. Both columns are their OWN `@container` so the section/field
 * grids inside break on the COLUMN's width, not the canvas'.
 */
const BODY_GRID_CLASS =
  'grid grid-cols-1 items-start gap-4 @5xl:grid-cols-[minmax(0,7fr)_minmax(0,4fr)]'
const COLUMN_CLASS = '@container flex min-w-0 flex-col gap-4'

interface QuoteDetailViewProps {
  quote: QuoteDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * The offer's collaboration card: Note | Documenti opportunità | Attività.
 *
 * The notes thread stays the parent Opportunity's (spec 0085 D-1) — the note is
 * SCOPED to this offer via `lockedQuoteId`, never moved onto another entity —
 * so no selector is offered here: the context is given.
 *
 * The documents are the parent Opportunity's, mounted READ-ONLY exactly as the
 * Contract detail mounts them (spec 0072 AC-047, user directive 2026-08-31
 * "quelli che trovo in contratti"): an offer never owns an attachment, so it
 * must not offer to add or remove one. The Attività tab is gated on its own
 * action flag and absent entirely when unauthorized.
 */
function QuoteDetailCollaboration({ quote }: { quote: QuoteDetailWithPermissions }) {
  const { t } = useTranslation()
  const canViewActivity = quote.permissions.actions.view_activity

  return (
    <RecordCard>
      <Tabs defaultValue={NOTES_TAB} className="gap-0">
        <div className="px-4 py-3">
          <TabsList>
            <TabsTrigger value={NOTES_TAB} className={TRIGGER_CLASS}>
              <MessagesSquare className="size-3.5" aria-hidden="true" />
              {t('notes.section.title')}
            </TabsTrigger>
            <TabsTrigger value={OPPORTUNITY_DOCUMENTS_TAB} className={TRIGGER_CLASS}>
              <Paperclip className="size-3.5" aria-hidden="true" />
              {t('quotes.detail.tabs.opportunityDocuments')}
            </TabsTrigger>
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
          <TabsContent value={NOTES_TAB}>
            <NotesSection
              entityType={REQUEST_MANAGEMENT_DOMAIN}
              entityId={quote.opportunity_id}
              showHeader={false}
              lockedQuoteId={quote.id}
            />
          </TabsContent>
          <TabsContent value={OPPORTUNITY_DOCUMENTS_TAB}>
            <DocumentsSection
              resource={OPPORTUNITY_ATTACHABLE_ALIAS}
              id={quote.opportunity_id}
              canUpload={false}
              canDelete={false}
            />
          </TabsContent>
          {canViewActivity ? (
            <TabsContent value={ACTIVITY_TAB}>
              <ActivityLogSection resource="quotes" id={quote.id} />
            </TabsContent>
          ) : null}
        </div>
      </Tabs>
    </RecordCard>
  )
}

/**
 * The record card's closing band (user directive: no card and no heading of its
 * own — the components sit directly in the record, and strip, rows and summary
 * share ONE band with no rule between them): the two line sets behind a tab
 * strip, then the persisted summary (`quote.summary`, D-9). No client
 * recomputation here, the server value is authoritative for an already-saved
 * quote. The summary sits inside `Tabs` but outside any `TabsContent`, so it
 * stays visible on both tabs — the same placement the form gives its live
 * preview. The strip stays controlled so it can hand the selection over to its
 * select fallback when the tabs no longer fit.
 */
function QuoteDetailLines({ quote }: { quote: QuoteDetailWithPermissions }) {
  const { t } = useTranslation()
  const [activeTab, setActiveTab] = useState(OFFER_TAB)
  const totals = totalsFromPersistedSummary(quote.summary)
  const showCommissions = quote.permissions.fields.commissions?.visible ?? true

  return (
    <Tabs value={activeTab} onValueChange={setActiveTab} className="gap-0">
      {/* Strip, righe e riepilogo nella STESSA banda, senza filetti in mezzo
          (direttiva utente): la strip etichetta la tabella che le sta sotto e
          il riepilogo ne e' il totale — un filetto li staccherebbe da cio' che
          descrivono. */}
      <div className="flex min-w-0 flex-col gap-3 border-t p-4">
        <FormTabStrip value={activeTab} onValueChange={setActiveTab}>
          <TabsTrigger value={OFFER_TAB} className={FORM_TAB_TRIGGER_CLASS}>
            <TrendingUp aria-hidden="true" />
            {t('quotes.form.tabs.offer')}
          </TabsTrigger>
          <TabsTrigger value={COSTS_TAB} className={FORM_TAB_TRIGGER_CLASS}>
            <TrendingDown aria-hidden="true" />
            {t('quotes.form.tabs.costs')}
          </TabsTrigger>
        </FormTabStrip>
        <TabsContent value={OFFER_TAB}>
          <QuoteLinesReadOnlyList lines={quote.offer_lines} showCommissions={showCommissions} />
        </TabsContent>
        <TabsContent value={COSTS_TAB}>
          <QuoteLinesReadOnlyList lines={quote.cost_lines} />
        </TabsContent>
        <QuoteSummary
          totals={totals}
          commissionTotals={{
            commercial: Number(quote.summary.commissions?.commercial ?? 0),
            reporter: Number(quote.summary.commissions?.reporter ?? 0),
            supervisor: Number(quote.summary.commissions?.supervisor ?? 0),
            supplier: Number(quote.summary.commissions?.supplier ?? 0),
          }}
        />
      </div>
    </Tabs>
  )
}

/**
 * Read-only detail of a single offer (spec 0065), rendered as an
 * enterprise-CRM record on the same `RecordCanvas` kit as
 * `/opportunities/:id`: on the left ONE card carrying identity + KPI strip +
 * titled sections and, as its closing band, the offer/cost rows with the
 * persisted summary; the collaboration card (note, attività) on the right; a
 * metadata footer below. Container-query driven, so the same tree renders
 * correctly both inside a resizable Sheet and on the full-bleed page.
 */
export function QuoteDetailView({ quote, onEdit }: QuoteDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(quote.created_at)
  const updatedAt = formatDateTime(quote.updated_at)

  return (
    <ResourcePermissionsProvider permissions={quote.permissions}>
      <RecordCanvas>
        <div className={BODY_GRID_CLASS}>
          <div className={COLUMN_CLASS}>
            <RecordCard>
              <QuoteDetailHeader quote={quote} onEdit={onEdit} />
              <QuoteDetailStats quote={quote} />
              <QuoteDetailSections quote={quote} />
              <QuoteDetailLines quote={quote} />
            </RecordCard>
          </div>

          <div className={COLUMN_CLASS}>
            <QuoteDetailCollaboration quote={quote} />
          </div>
        </div>

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
    </ResourcePermissionsProvider>
  )
}
