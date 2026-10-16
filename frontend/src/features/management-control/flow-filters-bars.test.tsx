import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import i18n from '@/i18n'
import { apiClient } from '@/api/client'
import { defaultCashFlowFilters, defaultTrendFilters } from '@/features/management-control/api'
import { CashFlowFiltersBar } from '@/features/management-control/cash-flow-filters-bar'
import { TrendFiltersBar } from '@/features/management-control/trend-filters-bar'

vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn() } }))

const getMock = vi.mocked(apiClient.get)

const EMPTY_FOR_SELECT = {
  items: [],
  export_link: null,
  pagination: { total: 0, offset: 0, limit: 25, total_pages: 0, has_more: false },
}

function renderWithClient(ui: ReactNode) {
  // One client per test: a per-render client would reset the cache on every render.
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  getMock.mockReset()
  getMock.mockResolvedValue({ data: EMPTY_FOR_SELECT })
})

describe('CashFlowFiltersBar', () => {
  it('AC-013 exposes scope, document type and horizon by accessible role, with no period', () => {
    renderWithClient(<CashFlowFiltersBar filters={defaultCashFlowFilters()} onChange={vi.fn()} />)

    for (const name of ['Company', 'Company site', 'Operational site']) {
      expect(screen.getByRole('button', { name })).toBeInTheDocument()
    }
    expect(screen.getByRole('combobox', { name: 'Document type' })).toHaveTextContent('All')
    expect(screen.getByRole('combobox', { name: 'Horizon' })).toHaveTextContent('6 months')
    expect(screen.queryByLabelText('to')).not.toBeInTheDocument()
  })

  it('AC-013 a document type change calls onChange with the updated filters', async () => {
    const onChange = vi.fn()
    const filters = defaultCashFlowFilters()
    renderWithClient(<CashFlowFiltersBar filters={filters} onChange={onChange} />)

    fireEvent.keyDown(screen.getByRole('combobox', { name: 'Document type' }), { key: 'ArrowDown' })
    fireEvent.click(await screen.findByRole('option', { name: 'Proforma' }))

    expect(onChange).toHaveBeenCalledWith({ ...filters, documentType: 'proforma' })
  })

  it('AC-013 the horizon selector offers 3, 6, 12 and 24 months and reports the choice', async () => {
    const onChange = vi.fn()
    const filters = defaultCashFlowFilters()
    renderWithClient(<CashFlowFiltersBar filters={filters} onChange={onChange} />)

    fireEvent.keyDown(screen.getByRole('combobox', { name: 'Horizon' }), { key: 'ArrowDown' })
    for (const name of ['3 months', '6 months', '12 months']) {
      expect(await screen.findByRole('option', { name })).toBeInTheDocument()
    }
    fireEvent.click(screen.getByRole('option', { name: '24 months' }))

    expect(onChange).toHaveBeenCalledWith({ ...filters, horizonMonths: 24 })
  })
})

describe('TrendFiltersBar', () => {
  it('AC-013 exposes the period and the scope by accessible role', () => {
    const filters = { ...defaultTrendFilters(), dateFrom: '2026-01-01', dateTo: '2026-06-30' }
    renderWithClient(<TrendFiltersBar filters={filters} onChange={vi.fn()} />)

    expect(screen.getByLabelText('Documents from')).toHaveValue('2026-01-01')
    expect(screen.getByLabelText('to')).toHaveValue('2026-06-30')
    for (const name of ['Company', 'Company site', 'Operational site']) {
      expect(screen.getByRole('button', { name })).toBeInTheDocument()
    }
  })

  it('AC-013 a period change calls onChange with the updated filters', () => {
    const onChange = vi.fn()
    const filters = { ...defaultTrendFilters(), dateFrom: '2026-01-01', dateTo: '2026-06-30' }
    renderWithClient(<TrendFiltersBar filters={filters} onChange={onChange} />)

    fireEvent.change(screen.getByLabelText('Documents from'), { target: { value: '2026-02-01' } })

    expect(onChange).toHaveBeenCalledWith({ ...filters, dateFrom: '2026-02-01' })
  })
})
