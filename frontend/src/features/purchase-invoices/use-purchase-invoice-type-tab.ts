import { useCallback, useMemo, useState, type RefObject } from 'react'
import type { TableViewHandle } from '@/features/table/table-view'
import { PURCHASE_INVOICE_TYPES, type PurchaseInvoiceType } from '@/features/purchase-invoices/types'

export const DOCUMENT_TYPE_FILTER_KEY = 'document_type'

export type PurchaseInvoiceTypeTab = 'all' | PurchaseInvoiceType

/** Tabs of the list, in display order. */
export const PURCHASE_INVOICE_TYPE_TABS: readonly PurchaseInvoiceTypeTab[] = ['all', 'proforma', 'invoice', 'payslip']

/** The list opens on the invoices (spec 0431, user decision 2026-11-25). */
const DEFAULT_TAB: PurchaseInvoiceType = 'invoice'

type FilterModel = Record<string, unknown>

function typeFilter(type: PurchaseInvoiceType): { filterType: 'set'; values: string[] } {
  return { filterType: 'set', values: [type] }
}

/** One single document type in the grid filter is that tab; none is "all"; anything else matches no tab. */
function readTab(model: FilterModel): PurchaseInvoiceTypeTab | null {
  const entry = model[DOCUMENT_TYPE_FILTER_KEY] as { values?: unknown } | undefined
  if (!Array.isArray(entry?.values) || entry.values.length === 0) return 'all'
  const only = entry.values.length === 1 ? String(entry.values[0]) : null
  return PURCHASE_INVOICE_TYPES.includes(only as PurchaseInvoiceType) ? (only as PurchaseInvoiceType) : null
}

export interface PurchaseInvoiceTypeTabState {
  tab: PurchaseInvoiceTypeTab | null
  /** Merged over the saved grid filters at mount, so the list opens on the invoices. */
  forcedFilterModel: FilterModel
  /** Mirror of the grid's live filter model (pass to `TableView.onFilterModelChange`). */
  onFilterModelChange: (model: FilterModel) => void
  setTab: (tab: PurchaseInvoiceTypeTab) => void
}

/**
 * The document type tabs of the passive documents list (Tutte / Proforma /
 * Fatture / Cedolini). The grid filter model stays the single source of
 * truth: the tabs read it and write through the table handle.
 */
export function usePurchaseInvoiceTypeTab(tableRef: RefObject<TableViewHandle | null>): PurchaseInvoiceTypeTabState {
  const forcedFilterModel = useMemo<FilterModel>(() => ({ [DOCUMENT_TYPE_FILTER_KEY]: typeFilter(DEFAULT_TAB) }), [])
  const [model, setModel] = useState<FilterModel>(forcedFilterModel)
  const tab = useMemo(() => readTab(model), [model])

  const setTab = useCallback(
    (next: PurchaseInvoiceTypeTab) =>
      tableRef.current?.setFilterModel({ [DOCUMENT_TYPE_FILTER_KEY]: next === 'all' ? null : typeFilter(next) }),
    [tableRef],
  )

  return { tab, forcedFilterModel, onFilterModelChange: setModel, setTab }
}
