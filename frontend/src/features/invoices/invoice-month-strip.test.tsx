import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useEffect, useRef } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { formatEuro } from '@/features/invoices/invoice-format'
import { InvoiceListToolbar } from '@/features/invoices/invoice-list-toolbar'
import { InvoiceTotalsFooter } from '@/features/invoices/invoice-totals-footer'
import { useInvoiceListFilters } from '@/features/invoices/use-invoice-list-filters'
import type { MonthlySummary } from '@/features/invoices/types'
import type { TableViewHandle } from '@/features/table/table-view'

const monthlySummaryMock = vi.fn<(params: { year: number; type: string }) => { data: MonthlySummary; isError: boolean }>()
vi.mock('@/features/invoices/use-invoice-queries', () => ({
  useMonthlySummary: (params: { year: number; type: string }) => monthlySummaryMock(params),
}))

const NOW = new Date()
const CURRENT_MONTH = NOW.getMonth() + 1
const OTHER_MONTH = CURRENT_MONTH === 1 ? 2 : 1

function buildSummary(): MonthlySummary {
  return {
    year: NOW.getFullYear(),
    months: Array.from({ length: 12 }, (_, index) => ({
      month: index + 1,
      count: index + 1 === OTHER_MONTH ? 3 : 0,
      total_amount: index + 1 === OTHER_MONTH ? '1500.00' : '0.00',
    })),
  }
}

const AGGREGATES = {
  net_amount: 1000,
  vat_amount: 220,
  total_amount: 1220,
  collected_amount: 500,
  residual_amount: 720,
}

const setFilterModelSpy = vi.fn<(patch: Record<string, unknown>) => void>()

/** Echo target set by the harness: the grid's "filter changed" event feeding the hook's mirror. */
let echoToHook: ((model: Record<string, unknown>) => void) | null = null
let gridModel: Record<string, unknown> = {}

/** Stands in for the grid: applies the patch to a model and echoes it back, like the table does. */
const fakeHandle: TableViewHandle = {
  refresh: vi.fn(),
  clearSelection: vi.fn(),
  setFilterModel: (patch) => {
    setFilterModelSpy(patch)
    const next = { ...gridModel, ...patch }
    for (const key of Object.keys(next)) {
      if (next[key] === null) {
        delete next[key]
      }
    }
    gridModel = next
    echoToHook?.(next)
  },
}

function Harness() {
  const handleRef = useRef<TableViewHandle | null>(fakeHandle)
  const filters = useInvoiceListFilters(handleRef)
  const { forcedFilterModel, onFilterModelChange } = filters

  useEffect(() => {
    gridModel = { ...forcedFilterModel }
    echoToHook = onFilterModelChange
  }, [forcedFilterModel, onFilterModelChange])

  return (
    <>
      <InvoiceListToolbar filters={filters} />
      <InvoiceTotalsFooter aggregates={AGGREGATES} />
    </>
  )
}

/** `Intl` separates the symbol with a no-break space; the DOM matcher compares normalized plain spaces. */
function plainEuro(value: string | number): string {
  return formatEuro(value).replace(/\p{Zs}/gu, ' ')
}

function monthButton(month: number) {
  return screen.getByRole('button', { name: i18n.t(`invoices.months.${month}`) })
}

describe('Invoice list toolbar and footer (spec 0194 AC-010)', () => {
  beforeEach(() => {
    setFilterModelSpy.mockClear()
    monthlySummaryMock.mockReturnValue({ data: buildSummary(), isError: false })
  })

  it('starts on the current month and toggles months into the document_month filter', () => {
    render(<Harness />)

    expect(monthButton(CURRENT_MONTH)).toHaveAttribute('aria-pressed', 'true')
    expect(monthButton(OTHER_MONTH)).toHaveAttribute('aria-pressed', 'false')
    expect(monthButton(OTHER_MONTH)).toHaveTextContent(plainEuro('1500.00'))

    fireEvent.click(monthButton(OTHER_MONTH))
    expect(setFilterModelSpy).toHaveBeenLastCalledWith({
      document_month: { filterType: 'set', values: [String(Math.min(CURRENT_MONTH, OTHER_MONTH)), String(Math.max(CURRENT_MONTH, OTHER_MONTH))] },
    })
    expect(monthButton(OTHER_MONTH)).toHaveAttribute('aria-pressed', 'true')

    fireEvent.click(monthButton(CURRENT_MONTH))
    fireEvent.click(monthButton(OTHER_MONTH))
    expect(setFilterModelSpy).toHaveBeenLastCalledWith({ document_month: null })
  })

  it('selects and deselects every month', () => {
    render(<Harness />)

    fireEvent.click(screen.getByRole('button', { name: i18n.t('invoices.monthStrip.selectAll') }))
    expect(setFilterModelSpy).toHaveBeenLastCalledWith({
      document_month: { filterType: 'set', values: Array.from({ length: 12 }, (_, index) => String(index + 1)) },
    })

    fireEvent.click(screen.getByRole('button', { name: i18n.t('invoices.monthStrip.deselectAll') }))
    expect(setFilterModelSpy).toHaveBeenLastCalledWith({ document_month: null })
  })

  it('writes the type filter from the tabs and re-reads the monthly summary for it', () => {
    render(<Harness />)

    expect(screen.getByRole('tab', { name: i18n.t('invoices.tabs.all') })).toHaveAttribute('aria-selected', 'true')

    fireEvent.click(screen.getByRole('tab', { name: i18n.t('invoices.tabs.proforma') }))
    expect(setFilterModelSpy).toHaveBeenLastCalledWith({ type: { filterType: 'set', values: ['proforma'] } })
    expect(screen.getByRole('tab', { name: i18n.t('invoices.tabs.proforma') })).toHaveAttribute('aria-selected', 'true')
    expect(monthlySummaryMock).toHaveBeenLastCalledWith({ year: NOW.getFullYear(), type: 'proforma' })

    fireEvent.click(screen.getByRole('tab', { name: i18n.t('invoices.tabs.all') }))
    expect(setFilterModelSpy).toHaveBeenLastCalledWith({ type: null })
  })

  it('renders the footer totals from meta.aggregates', () => {
    render(<Harness />)

    const footer = screen.getByRole('group', { name: i18n.t('invoices.footer.label') })
    expect(footer).toHaveTextContent(plainEuro(1220))
    expect(footer).toHaveTextContent(plainEuro(720))
    expect(footer).toHaveTextContent(i18n.t('invoices.footer.collected_amount'))
  })
})
