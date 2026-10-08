import { ChevronDown, Layers, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import type { GridApi } from 'ag-grid-community'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useRowGroupColumns } from '@/features/table/use-row-group-columns'
import type { TableColumn, TableRowGroupingConfig } from '@/features/table/types'

interface RowGroupingBarProps {
  gridApi: GridApi | null
  columns: TableColumn[]
  rowGrouping: TableRowGroupingConfig
}

/**
 * "Raggruppa per" strip of an opt-in row-grouping domain (spec 0197): an
 * explicit picker in place of AG Grid's drag-a-header-here panel, plus one
 * removable chip per active level in nesting order. Compact (ui-design.md §2).
 */
export function RowGroupingBar({ gridApi, columns, rowGrouping }: RowGroupingBarProps) {
  const { t } = useTranslation()
  const { groupedIds, toggle, remove, clear } = useRowGroupColumns(gridApi, rowGrouping.max_depth)
  const groupable = columns.filter((column) => column.groupable === true && rowGrouping.columns.includes(column.id))
  const labelOf = (id: string) => {
    const column = groupable.find((candidate) => candidate.id === id)
    return column ? t(column.label) : id
  }
  const isFull = groupedIds.length >= rowGrouping.max_depth

  if (groupable.length === 0) {
    return null
  }

  return (
    <div className="flex flex-wrap items-center gap-1.5 border-b border-border bg-card px-2.5 py-1.5">
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button type="button" size="xs" variant="secondary" disabled={!gridApi}>
            <Layers aria-hidden="true" />
            {t('table.grouping.groupBy')}
            <ChevronDown aria-hidden="true" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" className="min-w-48">
          <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
            {t('table.grouping.maxLevels', { count: rowGrouping.max_depth })}
          </DropdownMenuLabel>
          <DropdownMenuSeparator />
          {groupable.map((column) => {
            const checked = groupedIds.includes(column.id)
            return (
              <DropdownMenuCheckboxItem
                key={column.id}
                className="text-xs"
                checked={checked}
                disabled={!checked && isFull}
                onSelect={(event) => event.preventDefault()}
                onCheckedChange={() => toggle(column.id)}
              >
                {t(column.label)}
              </DropdownMenuCheckboxItem>
            )
          })}
        </DropdownMenuContent>
      </DropdownMenu>

      {groupedIds.length === 0 ? (
        <span className="text-xs text-muted-foreground">{t('table.grouping.none')}</span>
      ) : (
        <ol aria-label={t('table.grouping.activeLevels')} className="flex flex-wrap items-center gap-1">
          {groupedIds.map((id, index) => (
            <li
              key={id}
              className="inline-flex items-center gap-1 rounded-md border border-border bg-surface py-0.5 pl-2 pr-0.5 text-xs"
            >
              <span className="tabular-nums text-muted-foreground">{index + 1}.</span>
              {labelOf(id)}
              <Button
                type="button"
                size="icon"
                variant="ghost"
                className="size-6"
                aria-label={t('table.grouping.removeLevel', { label: labelOf(id) })}
                onClick={() => remove(id)}
              >
                <X className="size-3.5" aria-hidden="true" />
              </Button>
            </li>
          ))}
        </ol>
      )}

      {groupedIds.length > 0 ? (
        <Button type="button" size="xs" variant="ghost" onClick={clear}>
          {t('table.grouping.clear')}
        </Button>
      ) : null}
    </div>
  )
}
