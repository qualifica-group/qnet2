import { useState } from 'react'
import { ChevronsDown, ChevronsUp, Search } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { OrgChartCanvas, type OrgRowRenderers } from '@/features/org-chart/components/org-chart-canvas'
import { OrgMembersSheet } from '@/features/org-chart/components/org-members-sheet'
import type { OrgTreeNode } from '@/features/org-chart/tree-nodes'
import { findByKey } from '@/features/org-chart/tree-utils'
import { useOrgTreeState } from '@/features/org-chart/use-org-tree-state'
import { apiErrorMessage } from '@/lib/forbidden-message'

const SKELETON_ROWS = 6

interface OrgChartPanelProps extends OrgRowRenderers {
  nodes: OrgTreeNode[]
  isLoading: boolean
  /** The failed request's error, when there was one. */
  error: unknown
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
 * Shared shell of both views: toolbar (search, expand/collapse all), the
 * graphic chart and the members side panel, with the loading / forbidden /
 * error / empty states. The panel follows the card by key, so it shows fresh
 * data after a move.
 */
export function OrgChartPanel({
  nodes: allNodes,
  isLoading,
  isError,
  error,
  ariaLabel,
  emptyLabel,
  renderRowActions,
  wrapRow,
}: OrgChartPanelProps) {
  const { t, i18n } = useTranslation()
  const state = useOrgTreeState(allNodes)
  const [membersKey, setMembersKey] = useState<string | null>(null)
  const membersNode = membersKey === null ? null : findByKey(state.nodes, membersKey)

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
    const message = apiErrorMessage(error, t('orgChart.loadError'), i18n)
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
        <OrgChartCanvas
          nodes={state.nodes}
          collapsedKeys={state.collapsedKeys}
          onToggle={state.toggle}
          ariaLabel={ariaLabel}
          highlight={state.isSearching ? state.search.trim().toLowerCase() : ''}
          onOpenMembers={(node) => setMembersKey(node.key)}
          renderRowActions={renderRowActions}
          wrapRow={wrapRow}
        />
      )}
      <OrgMembersSheet node={membersNode} onClose={() => setMembersKey(null)} renderRowActions={renderRowActions} />
    </Card>
  )
}
