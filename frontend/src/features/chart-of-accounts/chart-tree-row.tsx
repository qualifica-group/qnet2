import type { KeyboardEvent } from 'react'
import { ChevronDown, ChevronRight } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { ChartTreeRowMenu } from '@/features/chart-of-accounts/chart-tree-row-menu'
import type { RowActionKey } from '@/features/chart-of-accounts/tree-actions'
import type { TreeRow } from '@/features/chart-of-accounts/tree-model'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { cn } from '@/lib/utils'

const INDENT_CLASS = ['pl-1', 'pl-6', 'pl-11'] as const

interface ChartTreeRowProps {
  row: TreeRow
  actions: RowActionKey[]
  /** While searching the open state is derived from the matches, so the chevron is not interactive. */
  locked: boolean
  onToggle: (key: string, open?: boolean) => void
  onAction: (action: RowActionKey, row: TreeRow) => void
}

interface RowText {
  code: string | null
  name: string
  inactive: boolean
}

function textOf(row: TreeRow, unplacedLabel: string): RowText {
  switch (row.type) {
    case 'unplaced':
      return { code: null, name: unplacedLabel, inactive: false }
    default:
      return { code: row.node.code, name: row.node.name, inactive: !row.node.is_active }
  }
}

/** One line of the plan: toggle, code, name, badges and the action menu. */
export function ChartTreeRow({ row, actions, locked, onToggle, onAction }: ChartTreeRowProps) {
  const { t } = useTranslation()
  const { code, name, inactive } = textOf(row, t('chartOfAccounts.unplaced.title'))
  const expandable = row.type !== 'sub' && row.expandable
  const expanded = row.type !== 'sub' && row.expanded
  const label = [code, name].filter(Boolean).join(' ')
  const isHeading = row.type === 'group' || row.type === 'unplaced'

  const onKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
    if (locked) return
    if (event.key === 'ArrowRight' && !expanded) {
      event.preventDefault()
      onToggle(row.key, true)
    } else if (event.key === 'ArrowLeft' && expanded) {
      event.preventDefault()
      onToggle(row.key, false)
    }
  }

  return (
    <li
      className={cn(
        'flex min-h-9 items-center gap-1.5 pr-2 hover:bg-muted/50',
        INDENT_CLASS[row.depth],
        isHeading && 'bg-surface font-medium',
        inactive && 'text-muted-foreground',
      )}
    >
      {expandable ? (
        <button
          type="button"
          disabled={locked}
          aria-expanded={expanded}
          aria-label={t(expanded ? 'chartOfAccounts.collapse' : 'chartOfAccounts.expand', { label })}
          onClick={() => onToggle(row.key)}
          onKeyDown={onKeyDown}
          className="flex size-6 shrink-0 items-center justify-center rounded-md outline-none hover:bg-accent focus-visible:ring-[2px] focus-visible:ring-ring/50 disabled:cursor-default disabled:hover:bg-transparent"
        >
          {expanded ? (
            <ChevronDown className="size-3.5" aria-hidden="true" />
          ) : (
            <ChevronRight className="size-3.5" aria-hidden="true" />
          )}
        </button>
      ) : (
        <span className="size-6 shrink-0" aria-hidden="true" />
      )}

      {code ? <span className="shrink-0 font-mono text-xs tabular-nums">{code}</span> : null}
      <span className={cn('min-w-0 flex-1 truncate text-sm', inactive && 'opacity-70')} title={name}>
        {name}
      </span>

      {row.type === 'group' ? (
        <Badge variant={row.node.kind === 'cost' ? 'secondary' : 'outline'} className="shrink-0 text-[11px]">
          {t(`chartOfAccounts.kind.${row.node.kind}`)}
        </Badge>
      ) : null}
      {row.type === 'unplaced' ? (
        <Badge variant="outline" className="shrink-0 text-[11px]">
          {row.count}
        </Badge>
      ) : null}
      {row.type === 'sub' && row.node.source === 'cost_type' && row.node.color ? (
        <span
          className={cn('size-3 shrink-0 rounded-full border', swatchClassFor(row.node.color) ?? 'bg-transparent')}
          aria-hidden="true"
        />
      ) : null}
      {inactive ? (
        <Badge variant="outline" className="shrink-0 text-[11px] font-normal">
          {t('chartOfAccounts.inactive')}
        </Badge>
      ) : null}

      <ChartTreeRowMenu label={label} actions={actions} onAction={(action) => onAction(action, row)} />
    </li>
  )
}
