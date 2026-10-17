import { useId, type ReactNode } from 'react'
import { ChevronDown, ChevronRight } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { OrgNodeContent } from '@/features/org-chart/components/org-node-content'
import type { OrgTreeNode } from '@/features/org-chart/tree-nodes'
import { useTreeNavigation } from '@/features/org-chart/use-tree-navigation'

/**
 * Hooks for the move feature (spec 0273 D-4/D-5). Both are optional and carry
 * no behaviour here: `renderRowActions` renders the per-row menu at the right
 * edge; `wrapRow` wraps the whole row (draggable / droppable surface).
 */
export interface OrgRowRenderers {
  renderRowActions?: (node: OrgTreeNode) => ReactNode
  wrapRow?: (node: OrgTreeNode, row: ReactNode) => ReactNode
}

interface OrgTreeProps extends OrgRowRenderers {
  nodes: OrgTreeNode[]
  collapsedKeys: ReadonlySet<string>
  onToggle: (key: string) => void
  ariaLabel: string
}

interface BranchProps extends OrgRowRenderers {
  nodes: OrgTreeNode[]
  depth: number
  idPrefix: string
  collapsedKeys: ReadonlySet<string>
  onToggle: (key: string) => void
  activeKey: string | null
  onItemFocus: ReturnType<typeof useTreeNavigation>['onItemFocus']
  onItemKeyDown: ReturnType<typeof useTreeNavigation>['onItemKeyDown']
}

function OrgTreeBranch({ nodes, depth, idPrefix, collapsedKeys, onToggle, activeKey, onItemFocus, onItemKeyDown, renderRowActions, wrapRow }: BranchProps) {
  const { t } = useTranslation()

  return (
    <>
      {nodes.map((node) => {
        const hasChildren = node.children.length > 0
        const expanded = hasChildren && !collapsedKeys.has(node.key)
        const contentId = `${idPrefix}-${node.key}`
        const row = (
          <div
            data-org-row=""
            className="flex items-center gap-1.5 rounded-md px-1 py-1 hover:bg-muted/40"
          >
            {hasChildren ? (
              <button
                type="button"
                tabIndex={-1}
                onClick={() => onToggle(node.key)}
                aria-label={expanded ? t('orgChart.collapse') : t('orgChart.expand')}
                className="flex size-5 shrink-0 items-center justify-center rounded-sm text-muted-foreground hover:bg-muted hover:text-foreground"
              >
                {expanded ? <ChevronDown className="size-3.5" /> : <ChevronRight className="size-3.5" />}
              </button>
            ) : (
              <span aria-hidden="true" className="size-5 shrink-0" />
            )}
            <div id={contentId} data-org-content="" className="min-w-0 flex-1">
              <OrgNodeContent node={node} />
            </div>
            {renderRowActions ? <div className="shrink-0">{renderRowActions(node)}</div> : null}
          </div>
        )

        return (
          <li
            key={node.key}
            role="treeitem"
            aria-level={depth + 1}
            aria-expanded={hasChildren ? expanded : undefined}
            aria-labelledby={contentId}
            tabIndex={node.key === activeKey ? 0 : -1}
            data-node-key={node.key}
            data-node-kind={node.kind}
            data-node-id={node.id ?? ''}
            onFocus={onItemFocus}
            onKeyDown={onItemKeyDown}
            className="outline-none [&:focus-visible>[data-org-row]]:ring-2 [&:focus-visible>[data-org-row]]:ring-ring"
          >
            {wrapRow ? wrapRow(node, row) : row}
            {expanded ? (
              <ul role="group" className="ml-2.5 border-l border-border/60 pl-2">
                <OrgTreeBranch
                  nodes={node.children}
                  depth={depth + 1}
                  idPrefix={idPrefix}
                  collapsedKeys={collapsedKeys}
                  onToggle={onToggle}
                  activeKey={activeKey}
                  onItemFocus={onItemFocus}
                  onItemKeyDown={onItemKeyDown}
                  renderRowActions={renderRowActions}
                  wrapRow={wrapRow}
                />
              </ul>
            ) : null}
          </li>
        )
      })}
    </>
  )
}

/** Accessible expandable tree (role tree/treeitem) shared by the Functions and People views. */
export function OrgTree({ nodes, collapsedKeys, onToggle, ariaLabel, renderRowActions, wrapRow }: OrgTreeProps) {
  const idPrefix = useId()
  const { containerRef, activeKey, onItemFocus, onItemKeyDown } = useTreeNavigation({ nodes, collapsedKeys, onToggle })

  return (
    <ul ref={containerRef} role="tree" aria-label={ariaLabel} className="flex flex-col gap-0.5">
      <OrgTreeBranch
        nodes={nodes}
        depth={0}
        idPrefix={idPrefix}
        collapsedKeys={collapsedKeys}
        onToggle={onToggle}
        activeKey={activeKey}
        onItemFocus={onItemFocus}
        onItemKeyDown={onItemKeyDown}
        renderRowActions={renderRowActions}
        wrapRow={wrapRow}
      />
    </ul>
  )
}
