import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { FileText, Plus } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { RecordCard, RecordCardHeader } from '@/components/detail/record-panel'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useAbilities } from '@/features/auth/use-abilities'
import { NotesDialog } from '@/features/notes/notes-dialog'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'
import { TableView } from '@/features/table/table-view'
import { QUOTES_ACTION_ICONS } from '@/features/quotes/action-icons'
import { quoteColumnRenderers } from '@/features/quotes/column-renderers'
import { QUOTES_DOMAIN } from '@/features/quotes/api'
import { useOpportunityQuotesPanel } from '@/features/opportunities/use-opportunity-quotes-panel'
import type { OpportunityDetail } from '@/features/opportunities/types'

export interface OpportunityQuotesSectionProps {
  opportunity: Pick<OpportunityDetail, 'id' | 'quotes_count' | 'single_quote_per_opportunity'>
}

interface CreateQuoteButtonProps {
  onClick: () => void
  /** True when the one-offer rule already bit: the button stays visible but inert. */
  blocked: boolean
  className?: string
}

/**
 * The "Crea Offerta" affordance. Defined at module level, never inside the
 * panel: the header and the empty state render the same button.
 *
 * When the category caps the opportunity at one offer and one exists, the
 * button is disabled and carries the reason — a disabled control with no
 * explanation reads as a bug. `title` + `aria-describedby` rather than a
 * tooltip primitive: a `disabled` button fires no pointer events, so a
 * `TooltipTrigger` wrapped around it would never open.
 */
function CreateQuoteButton({ onClick, blocked, className }: CreateQuoteButtonProps) {
  const { t } = useTranslation()
  const reasonId = useId()

  if (!blocked) {
    return (
      <Button size="sm" onClick={onClick} className={className}>
        <Plus aria-hidden="true" />
        {t('opportunities.detail.quotes.create')}
      </Button>
    )
  }

  const reason = t('opportunities.detail.quotes.singleQuoteBlocked')

  return (
    <span className={cn('inline-flex', className)} title={reason}>
      <Button size="sm" disabled aria-describedby={reasonId}>
        <Plus aria-hidden="true" />
        {t('opportunities.detail.quotes.create')}
      </Button>
      <span id={reasonId} className="sr-only">
        {reason}
      </span>
    </span>
  )
}

/**
 * Permission gate: absent entirely without `quotes.viewAny` (AC-041). Spec
 * 0083 D-5 removed the `requires_quote` eligibility gate — every opportunity
 * may have offers now. Kept as its own component, not an early-return inside
 * `OpportunityQuotesPanel`, so the panel — which mounts `useModuleOpener`
 * (and therefore `useNavigate`) unconditionally, per rules-of-hooks — is
 * never even instantiated when the permission is missing.
 */
export function OpportunityQuotesSection({ opportunity }: OpportunityQuotesSectionProps) {
  const { can } = useAbilities()

  if (!can('quotes.viewAny')) {
    return null
  }

  return <OpportunityQuotesPanel opportunity={opportunity} />
}

/**
 * Contextual view of the Quotes module inside the Opportunity detail (spec
 * 0067): the SAME `TableView domain="quotes"` grid, renderers, delete flow
 * and activity dialog as the standalone Quotes page (`QuotesTable`), scoped
 * to this opportunity's rows (`rowScope`) and forced into a Sheet for
 * create/view/edit (D-3) so the parent record is never abandoned. The "Crea
 * Offerta" affordance is gated separately by `quotes.create` in both the
 * header and the empty state (AC-040).
 */
function OpportunityQuotesPanel({ opportunity }: OpportunityQuotesSectionProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const canCreate = can('quotes.create')

  const {
    tableRef,
    count,
    showEmptyState,
    isBusy,
    handleAction,
    handleRowCountChanged,
    handleCreate,
    activityRow,
    closeActivity,
    notesTarget,
    closeNotes,
    createBlocked,
    refreshRows,
    sheet,
  } = useOpportunityQuotesPanel(
    opportunity.id,
    opportunity.quotes_count ?? 0,
    opportunity.single_quote_per_opportunity ?? false,
  )

  return (
    <RecordCard>
      <RecordCardHeader
        title={
          // Il conteggio sta sulla stessa riga del titolo, non nella fascia
          // `badges` sotto: e' una qualificazione del titolo, non uno stato.
          <span className="flex items-center gap-2">
            <span className="truncate">{t('opportunities.detail.quotes.title')}</span>
            <Badge
              variant="secondary"
              aria-label={t('opportunities.detail.quotes.countLabel', { count })}
            >
              {count}
            </Badge>
          </span>
        }
        actions={
          canCreate ? <CreateQuoteButton onClick={handleCreate} blocked={createBlocked} /> : null
        }
      />

      <div className="p-4">
        {showEmptyState ? (
          <div className="flex flex-col items-center gap-1.5 rounded-xl border border-dashed border-muted-foreground/25 bg-muted/20 px-4 py-8 text-center">
            <span className="flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground">
              <FileText className="size-5" aria-hidden="true" />
            </span>
            <p className="text-sm font-medium text-foreground">{t('opportunities.detail.quotes.empty')}</p>
            <p className="max-w-[32ch] text-xs text-muted-foreground">
              {t('opportunities.detail.quotes.emptyHint')}
            </p>
            {/*
             * The empty state can never be blocked (the rule only bites from
             * the SECOND offer on), but it goes through the same component so
             * the two affordances cannot drift.
             */}
            {canCreate ? (
              <CreateQuoteButton onClick={handleCreate} blocked={createBlocked} className="mt-2" />
            ) : null}
          </div>
        ) : (
          <TableView
            ref={tableRef}
            domain={QUOTES_DOMAIN}
            rowScope={{ opportunityId: opportunity.id }}
            renderers={quoteColumnRenderers}
            onAction={handleAction}
            isBusy={isBusy}
            iconMap={QUOTES_ACTION_ICONS}
            onRowCountChanged={handleRowCountChanged}
          />
        )}
      </div>

      {sheet}

      <ResourceActivityDialog resource={QUOTES_DOMAIN} row={activityRow} onOpenChange={closeActivity} />

      {/*
       * Spec 0085: la nota dell'Offerta vive sul thread dell'Opportunita'
       * padre — che qui e' il record ospite stesso — filtrata su quell'Offerta.
       */}
      <NotesDialog
        entityType={REQUEST_MANAGEMENT_DOMAIN}
        entityId={notesTarget?.opportunityId ?? null}
        lockedQuoteId={notesTarget?.quoteId ?? null}
        title={notesTarget?.code}
        onOpenChange={closeNotes}
        onThreadChanged={refreshRows}
      />
    </RecordCard>
  )
}
