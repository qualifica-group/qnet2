import { useCallback, useMemo, useState, type RefObject } from 'react'
import type { TableViewHandle } from '@/features/table/table-view'
import {
  INVOICE_TYPES,
  type InvoiceType,
  type InvoiceTypeFilter,
} from '@/features/invoices/types'

export const TYPE_FILTER_KEY = 'type'
export const YEAR_FILTER_KEY = 'document_year'
export const MONTH_FILTER_KEY = 'document_month'
const MONTHS_IN_YEAR = 12
export const ALL_MONTHS: readonly number[] = Array.from({ length: MONTHS_IN_YEAR }, (_, index) => index + 1)

type FilterModel = Record<string, unknown>

function setFilter(values: Array<string | number>): { filterType: 'set'; values: string[] } {
  return { filterType: 'set', values: values.map(String) }
}

function readValues(model: FilterModel, key: string): string[] {
  const entry = model[key] as { values?: unknown } | undefined
  return Array.isArray(entry?.values) ? entry.values.map(String) : []
}

function readType(model: FilterModel): InvoiceTypeFilter {
  const values = readValues(model, TYPE_FILTER_KEY)
  const only = values.length === 1 ? values[0] : null
  return INVOICE_TYPES.includes(only as InvoiceType) ? (only as InvoiceType) : 'all'
}

function readYear(model: FilterModel, fallback: number): number {
  const year = Number(readValues(model, YEAR_FILTER_KEY)[0])
  return Number.isInteger(year) ? year : fallback
}

function readMonths(model: FilterModel): number[] {
  return readValues(model, MONTH_FILTER_KEY)
    .map(Number)
    .filter((month) => Number.isInteger(month) && month >= 1 && month <= MONTHS_IN_YEAR)
    .sort((a, b) => a - b)
}

export interface InvoiceListFilters {
  type: InvoiceTypeFilter
  currentYear: number
  year: number
  /** Selected months (1..12); empty = the whole year. */
  months: number[]
  /** Merged over the saved grid filters at mount so grid and strip always agree. */
  forcedFilterModel: FilterModel
  /** Mirror of the grid's live filter model (pass to `TableView.onFilterModelChange`). */
  onFilterModelChange: (model: FilterModel) => void
  setType: (type: InvoiceTypeFilter) => void
  setYear: (year: number) => void
  toggleMonth: (month: number) => void
  selectAllMonths: () => void
  clearMonths: () => void
}

/**
 * The tabs / year / month strip state. The grid filter model is the single
 * source of truth (so "clear filters" chips and saved filters keep the strip
 * honest): the strip reads from it and writes through the table handle.
 */
export function useInvoiceListFilters(tableRef: RefObject<TableViewHandle | null>): InvoiceListFilters {
  const [now] = useState(() => new Date())
  const currentYear = now.getFullYear()
  const forcedFilterModel = useMemo<FilterModel>(
    () => ({
      [YEAR_FILTER_KEY]: setFilter([currentYear]),
      [MONTH_FILTER_KEY]: setFilter([now.getMonth() + 1]),
    }),
    [currentYear, now],
  )
  const [model, setModel] = useState<FilterModel>(forcedFilterModel)

  const type = useMemo(() => readType(model), [model])
  const year = useMemo(() => readYear(model, currentYear), [model, currentYear])
  const months = useMemo(() => readMonths(model), [model])

  const push = useCallback((patch: FilterModel) => tableRef.current?.setFilterModel(patch), [tableRef])

  const setType = useCallback(
    (next: InvoiceTypeFilter) => push({ [TYPE_FILTER_KEY]: next === 'all' ? null : setFilter([next]) }),
    [push],
  )
  const setYear = useCallback((next: number) => push({ [YEAR_FILTER_KEY]: setFilter([next]) }), [push])
  const writeMonths = useCallback(
    (next: number[]) => push({ [MONTH_FILTER_KEY]: next.length > 0 ? setFilter(next) : null }),
    [push],
  )
  const toggleMonth = useCallback(
    (month: number) =>
      writeMonths(
        months.includes(month)
          ? months.filter((value) => value !== month)
          : [...months, month].sort((a, b) => a - b),
      ),
    [months, writeMonths],
  )
  const selectAllMonths = useCallback(() => writeMonths([...ALL_MONTHS]), [writeMonths])
  const clearMonths = useCallback(() => writeMonths([]), [writeMonths])

  return {
    type,
    currentYear,
    year,
    months,
    forcedFilterModel,
    onFilterModelChange: setModel,
    setType,
    setYear,
    toggleMonth,
    selectAllMonths,
    clearMonths,
  }
}
