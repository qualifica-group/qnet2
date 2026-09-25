/**
 * Renders one AG Grid column filter model as the compact "valori" half of a
 * chip label ("Campo: valori", spec 0158 D-5). Pure, defensive formatting —
 * an unrecognized/malformed model degrades to an ellipsis rather than
 * throwing, since the shape comes from AG Grid's own internal filter state.
 */
import { formatBadgeFilterValue, formatBooleanFilterValue } from '@/components/data-table/column-filters'
import type { TableColumn } from '@/features/table/types'

/** Set-filter values beyond this count collapse into "+N" (spec 0158 D-5). */
const MAX_INLINE_VALUES = 3

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null
}

function formatSetValues(values: unknown[], column: TableColumn | undefined, translate: (key: string) => string): string {
  const labels = values.map((value) => {
    if (column?.type === 'boolean') {
      return formatBooleanFilterValue(value, translate)
    }
    if (column?.type === 'badge' || column?.type === 'tags') {
      return column ? formatBadgeFilterValue(value, column) : String(value ?? '')
    }
    return value === null ? translate('table.customFilters.blankValue') : String(value)
  })
  if (labels.length <= MAX_INLINE_VALUES) {
    return labels.join(', ')
  }
  const shown = labels.slice(0, MAX_INLINE_VALUES)
  return `${shown.join(', ')} +${labels.length - MAX_INLINE_VALUES}`
}

/** Describes a single (non-multi) condition model: `{filter, filterTo?}` or `{dateFrom, dateTo?}`. */
function formatConditionModel(model: Record<string, unknown>): string {
  const from = model.dateFrom ?? model.filter
  const to = model.dateTo ?? model.filterTo
  if (from != null && to != null) {
    return `${String(from)} – ${String(to)}`
  }
  if (from != null) {
    return String(from)
  }
  return ''
}

/**
 * Formats one column's active filter model into its chip's value text.
 * Handles the standalone Set Filter, a typed condition filter (text/number/
 * date, including range), and the combined `agMultiColumnFilter` (picks the
 * first sub-model that actually carries a value, preferring the Set tab).
 */
export function formatColumnFilterChipValue(
  model: unknown,
  column: TableColumn | undefined,
  translate: (key: string) => string,
): string {
  if (!isRecord(model)) {
    return ''
  }

  if (Array.isArray(model.values)) {
    return formatSetValues(model.values, column, translate)
  }

  if (Array.isArray(model.filterModels)) {
    const setModel = model.filterModels.find(
      (candidate) => isRecord(candidate) && Array.isArray(candidate.values),
    )
    if (isRecord(setModel) && Array.isArray(setModel.values)) {
      return formatSetValues(setModel.values, column, translate)
    }
    const conditionModel = model.filterModels.find((candidate) => isRecord(candidate))
    return isRecord(conditionModel) ? formatConditionModel(conditionModel) : ''
  }

  return formatConditionModel(model)
}
