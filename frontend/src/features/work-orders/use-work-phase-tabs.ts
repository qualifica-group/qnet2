import { useCallback, useMemo, useState, type RefObject } from 'react'
import type { TableViewHandle } from '@/features/table/table-view'
import type { TableRowsAggregates } from '@/features/table/types'
import type { WorkOrderWorkPhase } from '@/features/work-orders/types'

/** Grid filter key of the status tabs: the `status` column, the persisted working phase (spec 0395). */
export const WORK_PHASE_FILTER_KEY = 'status'
export const ALL_WORK_PHASES_TAB = 'all'
export const WORK_PHASES = ['to_plan', 'in_progress', 'completed', 'cancelled', 'terminated'] as const satisfies readonly WorkOrderWorkPhase[]

type WorkPhase = (typeof WORK_PHASES)[number]
export type WorkPhaseTab = WorkPhase | typeof ALL_WORK_PHASES_TAB

type FilterModel = Record<string, unknown>

function isWorkPhase(value: string): value is WorkPhase {
  return (WORK_PHASES as readonly string[]).includes(value)
}

/** The tab a grid filter model corresponds to; `null` when it matches none of them. */
function readWorkPhaseTab(model: FilterModel): WorkPhaseTab | null {
  const values = (model[WORK_PHASE_FILTER_KEY] as { values?: unknown } | undefined)?.values
  if (!Array.isArray(values) || values.length === 0) {
    return ALL_WORK_PHASES_TAB
  }
  const only = values.length === 1 ? String(values[0]) : null
  return only !== null && isWorkPhase(only) ? only : null
}

/**
 * The status tabs of the Commesse page: the grid filter model is the single
 * source of truth (so "clear filters" and saved filters keep the tabs honest);
 * the tabs read from it and write through the table handle. The counts come
 * with the rows, so they follow every other filter of the grid.
 */
export function useWorkPhaseTabs(tableRef: RefObject<TableViewHandle | null>) {
  const [model, setModel] = useState<FilterModel>({})
  const [counts, setCounts] = useState<TableRowsAggregates | undefined>(undefined)
  const active = useMemo(() => readWorkPhaseTab(model), [model])

  const select = useCallback(
    (tab: WorkPhaseTab) =>
      tableRef.current?.setFilterModel({
        [WORK_PHASE_FILTER_KEY]: tab === ALL_WORK_PHASES_TAB ? null : { filterType: 'set', values: [tab] },
      }),
    [tableRef],
  )

  return { active, counts, select, onFilterModelChange: setModel, onAggregatesChange: setCounts }
}
