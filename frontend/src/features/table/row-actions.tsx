/* eslint-disable react-refresh/only-export-components -- renderer factory module: returns an AG Grid cell renderer, not a route/page component */
import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { MoreHorizontal } from 'lucide-react'
import type { ICellRendererParams } from 'ag-grid-community'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { useConfirm } from '@/components/confirm-dialog-context'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import {
  resolveActionIcon,
  type ActionIconMap,
} from '@/features/table/action-icon-map'
import { ACTION_ICON_CLASS, actionMenuVariant } from '@/features/table/action-tone'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * How many actions render as inline icon buttons. With up to this many actions
 * they all show inline; beyond it, the first `INLINE_ACTION_LIMIT` stay inline
 * and every remaining action moves into the overflow (three-dots) menu, keeping
 * the actions column narrow while the most frequent actions stay one click away.
 */
export const INLINE_ACTION_LIMIT = 3

/** Fired when the user triggers an action on a row. */
export type RowActionHandler = (
  action: TableActionDefinition,
  row: TableRow,
) => void

/** Optional behaviors injected by the domain that owns the actions column. */
export interface RowActionsOptions {
  /** Returns true while a mutation (e.g. delete) is running for the row. */
  isBusy?: (row: TableRow) => boolean
  /** Adjusts the row before computing actions (e.g. drop self-delete). */
  decorateRow?: (row: TableRow) => TableRow
  /**
   * Domain-supplied icon names → Lucide components, merged over the shared
   * defaults. Lets a domain advertise new icons without touching generic code.
   */
  iconMap?: ActionIconMap
}

interface RowActionsProps extends RowActionsOptions {
  row: TableRow
  /** Full action catalog from the config (how to render each key). */
  catalog: TableActionDefinition[]
  onAction: RowActionHandler
}

/**
 * Reads `row[action.count_field]` and coerces it to a finite positive count,
 * or `null` when the action has no `count_field` or the value doesn't render
 * a badge (missing, non-numeric, zero). Centralizes the guard so both the
 * inline and overflow renderers agree on when a badge shows.
 */
function resolveActionCount(action: TableActionDefinition, row: TableRow): number | null {
  if (!action.count_field) {
    return null
  }
  const value = row[String(action.count_field)]
  const count = typeof value === 'number' ? value : Number(value)
  return Number.isFinite(count) && count > 0 ? count : null
}

/**
 * Small numeric badge overlaid on an inline icon button's top-right corner
 * (e.g. the "documents" row action's attachment count). Purely presentational;
 * the caller decides whether to render it at all (see `resolveActionCount`).
 */
function ActionCountBadge({ count }: { count: number }) {
  return (
    <span
      aria-hidden="true"
      className="pointer-events-none absolute -top-px -right-px flex h-2.5 min-w-2.5 items-center justify-center rounded-full bg-primary px-0.5 text-[7px] leading-none text-primary-foreground"
    >
      {count}
    </span>
  )
}

/**
 * Renders the actions available for a single row by crossing `row.actions`
 * (the per-row whitelist computed server-side) with the config action catalog
 * (label/icon/type/confirm). Actions not present in `row.actions` are never
 * shown. `confirm` actions ask for confirmation before invoking the handler.
 *
 * Domain-agnostic: the concrete behavior of each action lives in the domain's
 * `onAction` handler; this component only renders the affordance and crosses the
 * whitelist with the catalog.
 */
function RowActions({
  row,
  catalog,
  onAction,
  isBusy,
  decorateRow,
  iconMap,
}: RowActionsProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()

  const effectiveRow = decorateRow ? decorateRow(row) : row
  const busy = isBusy?.(effectiveRow) ?? false

  // Preserve catalog order; only keep actions allowed for this row.
  const available = useMemo(() => {
    const allowed = new Set(effectiveRow.actions)
    return catalog.filter((action) => allowed.has(action.key))
  }, [catalog, effectiveRow.actions])

  // Split into always-visible inline icons and the overflow remainder. With few
  // enough actions the overflow stays empty and everything renders inline.
  const { visible, overflow } = useMemo(() => {
    if (available.length <= INLINE_ACTION_LIMIT) {
      return { visible: available, overflow: [] as TableActionDefinition[] }
    }
    return {
      visible: available.slice(0, INLINE_ACTION_LIMIT),
      overflow: available.slice(INLINE_ACTION_LIMIT),
    }
  }, [available])

  if (available.length === 0) {
    return null
  }

  const handleSelect = async (action: TableActionDefinition) => {
    if (action.confirm) {
      const confirmed = await confirm({
        tone: 'destructive',
        title: t(action.label),
        description: t('table.confirmAction'),
      })
      if (!confirmed) {
        return
      }
    }
    onAction(action, effectiveRow)
  }

  // Always-visible actions render inline as compact icon buttons (label in
  // tooltip); the overflow remainder, if any, folds into the three-dots menu.
  return (
    <div className="flex h-full items-center justify-end gap-0.5">
      <TooltipProvider>
        {visible.map((action) => {
          const Icon = resolveActionIcon(action.icon, iconMap)
          const label = t(action.label)
          const count = resolveActionCount(action, effectiveRow)
          return (
            <Tooltip key={action.key}>
              <TooltipTrigger asChild>
                <Button
                  variant="ghost"
                  size="icon-xs"
                  aria-label={count !== null ? `${label} (${count})` : label}
                  disabled={busy}
                  onClick={() => handleSelect(action)}
                  className={cn('relative', ACTION_ICON_CLASS[action.type])}
                >
                  <Icon aria-hidden="true" />
                  {count !== null && <ActionCountBadge count={count} />}
                </Button>
              </TooltipTrigger>
              <TooltipContent>{label}</TooltipContent>
            </Tooltip>
          )
        })}
      </TooltipProvider>

      {overflow.length > 0 && (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button
              variant="ghost"
              size="icon-xs"
              aria-label={t('table.moreActions')}
              disabled={busy}
            >
              <MoreHorizontal aria-hidden="true" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            {overflow.map((action) => {
              const Icon = resolveActionIcon(action.icon, iconMap)
              const count = resolveActionCount(action, effectiveRow)
              return (
                <DropdownMenuItem
                  key={action.key}
                  variant={actionMenuVariant(action.type)}
                  onSelect={() => handleSelect(action)}
                >
                  <Icon aria-hidden="true" />
                  {t(action.label)}
                  {count !== null && (
                    <span className="text-muted-foreground">({count})</span>
                  )}
                </DropdownMenuItem>
              )
            })}
          </DropdownMenuContent>
        </DropdownMenu>
      )}
    </div>
  )
}

/**
 * Factory that returns an AG Grid cell renderer for the row-actions column,
 * bound to the action catalog and a single action handler.
 */
export function createRowActionsRenderer(
  catalog: TableActionDefinition[],
  onAction: RowActionHandler,
  options: RowActionsOptions = {},
) {
  return function RowActionsCell(params: ICellRendererParams) {
    const row = params.data as TableRow | undefined
    if (!row) {
      return null
    }
    return (
      <RowActions
        row={row}
        catalog={catalog}
        onAction={onAction}
        isBusy={options.isBusy}
        decorateRow={options.decorateRow}
        iconMap={options.iconMap}
      />
    )
  }
}
