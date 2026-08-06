import { useTranslation } from 'react-i18next'
import { FileText, Plus } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { RecordCard, RecordCardHeader } from '@/components/detail/record-panel'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useAbilities } from '@/features/auth/use-abilities'
import { TableView } from '@/features/table/table-view'
import { quoteColumnRenderers } from '@/features/quotes/column-renderers'
import { QUOTES_DOMAIN } from '@/features/quotes/api'
import { useOpportunityQuotesPanel } from '@/features/opportunities/use-opportunity-quotes-panel'
import type { OpportunityDetail } from '@/features/opportunities/types'

export interface OpportunityQuotesSectionProps {
  opportunity: Pick<OpportunityDetail, 'id' | 'quotes_count'>
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
    sheet,
  } = useOpportunityQuotesPanel(opportunity.id, opportunity.quotes_count ?? 0)

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
          canCreate ? (
            <Button size="sm" onClick={handleCreate}>
              <Plus aria-hidden="true" />
              {t('opportunities.detail.quotes.create')}
            </Button>
          ) : null
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
            {canCreate ? (
              <Button size="sm" onClick={handleCreate} className="mt-2">
                <Plus aria-hidden="true" />
                {t('opportunities.detail.quotes.create')}
              </Button>
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
            onRowCountChanged={handleRowCountChanged}
          />
        )}
      </div>

      {sheet}

      <ResourceActivityDialog resource={QUOTES_DOMAIN} row={activityRow} onOpenChange={closeActivity} />
    </RecordCard>
  )
}
