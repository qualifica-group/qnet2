import { useId, useMemo, type ReactNode } from 'react'
import { ChevronUp, Maximize, Minus, Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { OrgChartCard } from '@/features/org-chart/components/org-chart-card'
import { CARD_HEIGHT, CARD_WIDTH, layoutChart, TOGGLE_OFFSET, type ChartCard } from '@/features/org-chart/chart-layout'
import { chartChildren, type OrgTreeNode } from '@/features/org-chart/tree-nodes'
import { MAX_ZOOM, MIN_ZOOM, useChartViewport } from '@/features/org-chart/use-chart-viewport'
import { useExitingCards } from '@/features/org-chart/use-exiting-cards'
import { useTreeNavigation } from '@/features/org-chart/use-tree-navigation'

/** SVG `d` transition (Safari may skip it); the cards move with `transition-[left,top]`. */
const EDGE_CLASS = 'stroke-border [transition:d_300ms_ease-out] motion-reduce:transition-none motion-reduce:animate-none'
const EXIT_CLASS = 'animate-out fade-out zoom-out-95 duration-200 fill-mode-forwards motion-reduce:animate-none'

/**
 * Hooks for the move feature (spec 0273 D-4/D-5). Both are optional and carry
 * no behaviour here: `renderRowActions` renders the per-card (or per-member)
 * menu; `wrapRow` wraps the card (draggable / droppable surface).
 */
export interface OrgRowRenderers {
  renderRowActions?: (node: OrgTreeNode) => ReactNode
  wrapRow?: (node: OrgTreeNode, row: ReactNode) => ReactNode
}

interface OrgChartCanvasProps extends OrgRowRenderers {
  nodes: OrgTreeNode[]
  collapsedKeys: ReadonlySet<string>
  onToggle: (key: string) => void
  ariaLabel: string
  /** Lower-cased search text: matching cards are highlighted. */
  highlight: string
  onOpenMembers: (node: OrgTreeNode) => void
}

/** Roots that head a hierarchy come first, unconnected cards after: the chart opens on the real structure. */
function hierarchiesFirst(nodes: OrgTreeNode[]): OrgTreeNode[] {
  const heads = nodes.filter((node) => chartChildren(node).length > 0)
  return heads.length === 0 || heads.length === nodes.length ? nodes : [...heads, ...nodes.filter((node) => chartChildren(node).length === 0)]
}

function TogglePill({ card, onToggle }: { card: ChartCard; onToggle: (key: string) => void }) {
  const { t } = useTranslation()
  return (
    <button
      type="button"
      tabIndex={-1}
      onClick={() => onToggle(card.node.key)}
      aria-label={card.expanded ? t('orgChart.collapse') : t('orgChart.expand')}
      style={{ top: CARD_HEIGHT + TOGGLE_OFFSET }}
      className="absolute left-1/2 flex h-5 min-w-8 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-primary px-2 text-[11px] font-medium text-primary-foreground shadow-sm hover:bg-primary/90"
    >
      {card.expanded ? <ChevronUp aria-hidden="true" className="size-3.5" /> : `+${card.hiddenCount}`}
    </button>
  )
}

interface ZoomControlsProps {
  zoom: number
  onZoomIn: () => void
  onZoomOut: () => void
  onFit: () => void
}

function ZoomControls({ zoom, onZoomIn, onZoomOut, onFit }: ZoomControlsProps) {
  const { t } = useTranslation()
  return (
    <div className="absolute right-2 bottom-2 z-10 flex items-center gap-0.5 rounded-lg border border-border bg-card p-0.5 shadow-sm">
      <Button type="button" variant="ghost" size="icon-xs" onClick={onZoomOut} disabled={zoom <= MIN_ZOOM} aria-label={t('orgChart.zoom.out')}>
        <Minus aria-hidden="true" />
      </Button>
      <span className="w-9 text-center text-[11px] text-muted-foreground tabular-nums" aria-live="polite">
        {Math.round(zoom * 100)}%
      </span>
      <Button type="button" variant="ghost" size="icon-xs" onClick={onZoomIn} disabled={zoom >= MAX_ZOOM} aria-label={t('orgChart.zoom.in')}>
        <Plus aria-hidden="true" />
      </Button>
      <Button type="button" variant="ghost" size="icon-xs" onClick={onFit} aria-label={t('orgChart.zoom.fit')} title={t('orgChart.zoom.fit')}>
        <Maximize aria-hidden="true" />
      </Button>
    </div>
  )
}

/**
 * Graphic org chart (spec 0274): cards laid out top-down by `layoutChart`,
 * joined by SVG curves, with zoom and pan. Cards are flat `treeitem`s
 * (level / set size / position) driven by the same keyboard model as before.
 */
export function OrgChartCanvas({
  nodes: treeNodes,
  collapsedKeys,
  onToggle,
  ariaLabel,
  highlight,
  onOpenMembers,
  renderRowActions,
  wrapRow,
}: OrgChartCanvasProps) {
  const idPrefix = useId()
  const nodes = useMemo(() => hierarchiesFirst(treeNodes), [treeNodes])
  const layout = useMemo(() => layoutChart(nodes, collapsedKeys), [nodes, collapsedKeys])
  const exiting = useExitingCards(layout.cards, layout.edges)
  const { viewportRef, zoom, zoomIn, zoomOut, fit, panHandlers } = useChartViewport(layout.width, layout.height)
  const { containerRef, activeKey, onItemFocus, onItemKeyDown } = useTreeNavigation({ nodes, collapsedKeys, onToggle })

  return (
    // contain-inline-size: the canvas' explicit width must not widen the page, it scrolls inside the viewport.
    <div className="relative w-full contain-inline-size">
      <div
        ref={viewportRef}
        {...panHandlers}
        className="max-h-[75vh] min-h-64 cursor-grab overflow-auto bg-surface active:cursor-grabbing"
      >
        <div style={{ width: layout.width * zoom, height: layout.height * zoom }}>
          <div
            ref={containerRef}
            role="tree"
            aria-label={ariaLabel}
            className="relative origin-top-left"
            style={{ width: layout.width, height: layout.height, transform: `scale(${zoom})` }}
          >
            <svg aria-hidden="true" width={layout.width} height={layout.height} className="pointer-events-none absolute inset-0">
              {layout.edges.map((edge) => (
                <path key={edge.key} d={edge.path} fill="none" strokeWidth={1.5} className={cn(EDGE_CLASS, 'animate-in fade-in')} />
              ))}
              {exiting.edges.map((edge) => (
                <path key={edge.key} d={edge.path} fill="none" strokeWidth={1.5} className={cn(EDGE_CLASS, EXIT_CLASS)} />
              ))}
            </svg>
            {exiting.cards.map((card) => (
              <div
                key={`exit:${card.node.key}`}
                aria-hidden="true"
                inert
                style={{ left: card.x, top: card.y, width: CARD_WIDTH, height: CARD_HEIGHT }}
                className={cn('pointer-events-none absolute rounded-lg', EXIT_CLASS)}
              >
                <OrgChartCard node={card.node} titleId={`${idPrefix}-exit-${card.node.key}`} highlighted={false} actions={null} onOpenMembers={onOpenMembers} />
              </div>
            ))}
            {layout.cards.map((card) => {
              const { node } = card
              const titleId = `${idPrefix}-${node.key}`
              const content = (
                <OrgChartCard
                  node={node}
                  titleId={titleId}
                  highlighted={highlight !== '' && node.searchText.includes(highlight)}
                  actions={renderRowActions?.(node)}
                  onOpenMembers={onOpenMembers}
                />
              )
              return (
                <div
                  key={node.key}
                  role="treeitem"
                  aria-level={card.level}
                  aria-setsize={card.setSize}
                  aria-posinset={card.posInSet}
                  aria-expanded={card.hasChildren ? card.expanded : undefined}
                  aria-labelledby={titleId}
                  tabIndex={node.key === activeKey ? 0 : -1}
                  data-node-key={node.key}
                  data-node-kind={node.kind}
                  data-node-id={node.id ?? ''}
                  onFocus={onItemFocus}
                  onKeyDown={onItemKeyDown}
                  style={{ left: card.x, top: card.y, width: CARD_WIDTH, height: CARD_HEIGHT }}
                  className="absolute cursor-default rounded-lg outline-none transition-[left,top] duration-300 ease-out animate-in fade-in zoom-in-95 motion-reduce:transition-none motion-reduce:animate-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                  {wrapRow ? wrapRow(node, content) : content}
                  {card.hasChildren ? <TogglePill card={card} onToggle={onToggle} /> : null}
                </div>
              )
            })}
          </div>
        </div>
      </div>
      <ZoomControls zoom={zoom} onZoomIn={zoomIn} onZoomOut={zoomOut} onFit={fit} />
    </div>
  )
}
