import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { useAbilities } from '@/features/auth/use-abilities'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { NotesDialog } from '@/features/notes/notes-dialog'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import { ATTRIBUTE_COLUMNS_FROM_FILTERS_SCOPE } from '@/features/table/use-table-config'
import { quoteColumnRenderers } from '@/features/quotes/column-renderers'
import { QUOTES_ACTION_ICONS } from '@/features/quotes/action-icons'
import { QUOTE_CREATE_WITH_OPPORTUNITY_PARAM } from '@/features/quotes/quote-create-params'
import { QuoteNewModeDialog } from '@/features/quotes/quote-new-mode-dialog'
import { QUOTES_DOMAIN } from '@/features/quotes/api'
import { quoteActionHref, useQuoteRowActions } from '@/features/quotes/use-quote-row-actions'

/**
 * Thin Quotes adapter over the generic table (spec 0065, mirrors
 * `OpportunitiesTable`/`ProductsTable`). It mounts `<TableView>` with the
 * `quotes` domain (AC-078: no columns declared client-side), its custom cell
 * renderers and the shared Quotes action wiring (`useQuoteRowActions` — the
 * same hook the Opportunity master/detail panel uses, so the two action sets
 * cannot drift), and delegates the open mode (modal Sheet vs dedicated page)
 * of view/edit/create to `useModuleOpener`, resolved from the user's
 * preference (spec 0042). No stats panel, no documents dialog (both
 * explicitly out of scope, spec 0065 `<out>`). Permission gating is an
 * affordance only; the backend re-authorizes each call.
 */
export function QuotesTable() {
  const { t } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => {
    tableRef.current?.refresh()
  }, [])

  const {
    handleAction,
    isBusy,
    activityRow,
    closeActivity,
    notesTarget,
    closeNotes,
    documentsDialog,
    sheet,
    openCreate,
    openCreateWith,
  } = useQuoteRowActions({ onMutated: refreshGrid })

  // Spec 0396 D-1: who may also create opportunities chooses how to start; everyone else goes straight to the form.
  const { can } = useAbilities()
  const [isChooserOpen, setIsChooserOpen] = useState(false)
  const handleNewQuote = () => (can('opportunities.create') ? setIsChooserOpen(true) : openCreate())
  const startWith = (open: () => void) => () => {
    setIsChooserOpen(false)
    open()
  }

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="quotes.create">
            <Button onClick={handleNewQuote}>
              <Plus aria-hidden="true" />
              {t('quotes.form.newQuote')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={QUOTES_DOMAIN}
        scope={ATTRIBUTE_COLUMNS_FROM_FILTERS_SCOPE}
        renderers={quoteColumnRenderers}
        onAction={handleAction}
        resolveActionHref={quoteActionHref}
        isBusy={isBusy}
        iconMap={QUOTES_ACTION_ICONS}
      />

      <QuoteNewModeDialog
        open={isChooserOpen}
        onOpenChange={setIsChooserOpen}
        onExisting={startWith(openCreate)}
        onWithOpportunity={startWith(() => openCreateWith({ [QUOTE_CREATE_WITH_OPPORTUNITY_PARAM]: 1 }))}
      />

      {sheet}

      {documentsDialog}

      <ResourceActivityDialog
        resource={QUOTES_DOMAIN}
        row={activityRow}
        onOpenChange={closeActivity}
      />

      <NotesDialog
        entityType={REQUEST_MANAGEMENT_DOMAIN}
        entityId={notesTarget?.opportunityId ?? null}
        lockedQuoteId={notesTarget?.quoteId ?? null}
        title={notesTarget?.code}
        onOpenChange={closeNotes}
        onThreadChanged={refreshGrid}
      />
    </div>
  )
}
