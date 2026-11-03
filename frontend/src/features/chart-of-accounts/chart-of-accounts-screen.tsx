import { Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/page-header'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ChartSheets } from '@/features/chart-of-accounts/chart-sheets'
import { ChartTreeRow } from '@/features/chart-of-accounts/chart-tree-row'
import { ChartTreeToolbar } from '@/features/chart-of-accounts/chart-tree-toolbar'
import { rowActions } from '@/features/chart-of-accounts/tree-actions'
import type { ChartTree } from '@/features/chart-of-accounts/types'
import { useChartScreenActions } from '@/features/chart-of-accounts/use-chart-screen-actions'
import { useChartTree } from '@/features/chart-of-accounts/use-chart-tree'
import { useTreeViewState } from '@/features/chart-of-accounts/use-tree-view-state'

const SKELETON_ROWS = ['a', 'b', 'c', 'd', 'e']

function ChartTreeView({ tree }: { tree: ChartTree }) {
  const { t } = useTranslation()
  const view = useTreeViewState(tree)
  const { sheet, closeSheet, openNewGroup, handleAction } = useChartScreenActions()
  const canCreateGroup = tree.abilities.chart_accounts.create

  return (
    <div className="flex flex-1 flex-col gap-3">
      <PageHeader
        actions={
          canCreateGroup ? (
            <Button size="sm" onClick={openNewGroup}>
              <Plus aria-hidden="true" />
              {t('chartOfAccounts.newGroup')}
            </Button>
          ) : null
        }
      />
      <ChartTreeToolbar
        query={view.query}
        onQueryChange={view.setQuery}
        onExpandAll={view.expandAll}
        onCollapseAll={view.collapseAll}
        expansionDisabled={view.isSearching}
      />
      {view.rows.length === 0 ? (
        <p className="rounded-lg border bg-card p-4 text-sm text-muted-foreground">
          {t(view.isSearching ? 'chartOfAccounts.search.empty' : 'chartOfAccounts.empty')}
        </p>
      ) : (
        <ul className="divide-y overflow-hidden rounded-lg border bg-card shadow-sm" aria-label={t('chartOfAccounts.treeLabel')}>
          {view.rows.map((row) => (
            <ChartTreeRow
              key={row.key}
              row={row}
              actions={rowActions(row, tree.abilities)}
              locked={view.isSearching}
              onToggle={view.toggle}
              onAction={handleAction}
            />
          ))}
        </ul>
      )}
      <ChartSheets sheet={sheet} groups={tree.groups} onClose={closeSheet} />
    </div>
  )
}

/** "Piano dei conti" screen (spec 0348): loads the whole plan once, everything else is client-side. */
export function ChartOfAccountsScreen() {
  const { t } = useTranslation()
  const query = useChartTree()

  if (query.isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('chartOfAccounts.loadError')}
        </p>
        <Button variant="outline" size="sm" className="bg-card" onClick={() => void query.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (!query.data) {
    return (
      <div className="flex flex-col gap-2" aria-busy="true">
        {SKELETON_ROWS.map((id) => (
          <Skeleton key={id} className="h-9 w-full" />
        ))}
      </div>
    )
  }

  return <ChartTreeView tree={query.data} />
}
