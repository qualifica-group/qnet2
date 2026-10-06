import { useCallback, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { useAbilities } from '@/features/auth/use-abilities'
import { ModuleStatsPanel } from '@/features/stats/module-stats-panel'
import { StatsToggleButton } from '@/features/stats/stats-toggle-button'
import { useStatsPanel } from '@/features/stats/use-stats-panel'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import { OPPORTUNITIES_ACTION_ICONS } from '@/features/opportunities/action-icons'
import { opportunityColumnRenderers } from '@/features/opportunities/column-renderers'
import { OpportunityQuotesDetailRenderer } from '@/features/opportunities/opportunity-quotes-detail-renderer'
import { OPPORTUNITIES_DOMAIN } from '@/features/opportunities/api'
import { useOpportunityRowActions } from '@/features/opportunities/use-opportunity-row-actions'

/**
 * Thin Opportunities adapter over the generic table (spec 0040, mirrors
 * Leads). It mounts `<TableView>` with the `opportunities` domain, its custom
 * cell renderers and a row-action handler, and delegates the open mode (modal
 * Sheet vs dedicated page) of view/edit/create to `useModuleOpener`, resolved
 * from the user's preference (spec 0042). Every row action's behavior lives in
 * `useOpportunityRowActions`, shared with the anagrafica detail's Opportunita'
 * tab (spec 0199); this adapter refreshes the SSRM grid and the stats panel
 * after every mutation. Permission gating is an affordance only; the backend
 * re-authorizes each call.
 *
 * Row actions mirror Gestione Richieste exactly (user directive 2026-08-05):
 * `view`, `documents`, `notes` inline, `delete`/`activity` in the overflow —
 * `edit` is NOT among them, the detail surface owns the Edit button
 * (`detailOwnsEditAction`, still gated by `opportunities.update`).
 */
export function OpportunitiesTable() {
  const { t } = useTranslation()
  // The expandable Offerte panel (master/detail) is an affordance only: without
  // `quotes.viewAny` the expand chevron is not rendered at all, and the panel's
  // own requests would be refused server-side anyway.
  const { can } = useAbilities()
  const canViewQuotes = can('quotes.viewAny')
  const stats = useStatsPanel(OPPORTUNITIES_DOMAIN)
  const invalidateStats = useInvalidateModuleStats(OPPORTUNITIES_DOMAIN)

  const tableRef = useRef<TableViewHandle>(null)

  // Every mutation (modal save, delete, documents/notes change) refreshes the
  // grid and the stats panel; page mode never calls it. The detail query is
  // invalidated inside `OpportunityFormScreen`.
  const handleMutated = useCallback(() => {
    tableRef.current?.refresh()
    invalidateStats()
  }, [invalidateStats])

  const { handleAction, isBusy, openCreate, sheet, dialogs } = useOpportunityRowActions({ onMutated: handleMutated })

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <StatsToggleButton
              domain={OPPORTUNITIES_DOMAIN}
              isOpen={stats.isOpen}
              onToggle={stats.toggle}
            />
            <Can permission="opportunities.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('opportunities.form.newOpportunity')}
              </Button>
            </Can>
          </>
        }
      />

      <ModuleStatsPanel domain={OPPORTUNITIES_DOMAIN} isOpen={stats.isOpen} />

      <TableView
        ref={tableRef}
        domain={OPPORTUNITIES_DOMAIN}
        renderers={opportunityColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
        iconMap={OPPORTUNITIES_ACTION_ICONS}
        masterDetail={canViewQuotes}
        detailCellRenderer={OpportunityQuotesDetailRenderer}
        detailRowAutoHeight
      />

      {sheet}

      {dialogs}
    </div>
  )
}
