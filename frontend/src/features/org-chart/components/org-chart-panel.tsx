import { ChevronsDown, ChevronsUp, Search } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { OrgTree, type OrgRowRenderers } from '@/features/org-chart/components/org-tree'
import type { OrgTreeNode } from '@/features/org-chart/tree-nodes'
import { useOrgTreeState } from '@/features/org-chart/use-org-tree-state'

const FORBIDDEN_STATUS = 403
const SKELETON_ROWS = 6

interface OrgChartPanelProps extends OrgRowRenderers {
  nodes: OrgTreeNode[]
  isLoading: boolean
  /** HTTP status of the failed request, when there was one. */
  errorStatus: number | null
  isError: boolean
  ariaLabel: string
  emptyLabel: string
}

function PanelMessage({ children, tone = 'muted' }: { children: string; tone?: 'muted' | 'destructive' }) {
  return (
    <p
      role={tone === 'destructive' ? 'alert' : undefined}
      className={tone === 'destructive' ? 'p-4 text-sm text-destructive' : 'p-6 text-center text-sm text-muted-foreground'}
    >
      {children}
    </p>
  )
}

/**
 * Shared shell of both views: toolbar (search, expand/collapse all) plus the
 * tree, with the loading / forbidden / error / empty states.
 */
export function OrgChartPanel({
  nodes: allNodes,
  isLoading,
  isError,
  errorStatus,
  ariaLabel,
  emptyLabel,
  renderRowActions,
  wrapRow,
}: OrgChartPanelProps) {
  const { t } = useTranslation()
  const state = useOrgTreeState(allNodes)

  if (isLoading) {
    return (
      <Card className="gap-2 p-3" aria-busy="true" aria-label={t('orgChart.loading')}>
        {Array.from({ length: SKELETON_ROWS }, (_, index) => (
          <Skeleton key={index} className="h-6 w-full" />
        ))}
      </Card>
    )
  }

  if (isError) {
    const message = errorStatus === FORBIDDEN_STATUS ? t('orgChart.forbidden') : t('orgChart.loadError')
    return (
      <Card>
        <PanelMessage tone="destructive">{message}</PanelMessage>
      </Card>
    )
  }

  const ToggleIcon = state.allCollapsed ? ChevronsDown : ChevronsUp

  return (
    <Card className="gap-0 overflow-hidden py-0">
      <div className="flex flex-wrap items-center gap-2 border-b border-border bg-surface px-3 py-2">
        <div className="relative min-w-0 flex-1 basis-48 sm:max-w-xs">
          <Search aria-hidden="true" className="pointer-events-none absolute top-1/2 left-2 size-3.5 -translate-y-1/2 text-muted-foreground" />
          <Input
            type="search"
            value={state.search}
            onChange={(event) => state.setSearch(event.target.value)}
            placeholder={t('orgChart.searchPlaceholder')}
            aria-label={t('orgChart.search')}
            className="h-8 pl-7 text-sm"
          />
        </div>
        <Button
          type="button"
          variant="secondary"
          size="sm"
          className="ml-auto gap-1.5"
          onClick={state.allCollapsed ? state.expandAll : state.collapseAll}
          disabled={!state.hasExpandable || state.isSearching}
        >
          <ToggleIcon className="size-3.5" aria-hidden="true" />
          {state.allCollapsed ? t('orgChart.expandAll') : t('orgChart.collapseAll')}
        </Button>
      </div>
      {state.nodes.length === 0 ? (
        <PanelMessage>{state.isSearching ? t('orgChart.noMatch') : emptyLabel}</PanelMessage>
      ) : (
        <div className="max-h-[70vh] overflow-auto p-2">
          <OrgTree
            nodes={state.nodes}
            collapsedKeys={state.collapsedKeys}
            onToggle={state.toggle}
            ariaLabel={ariaLabel}
            renderRowActions={renderRowActions}
            wrapRow={wrapRow}
          />
        </div>
      )}
    </Card>
  )
}
