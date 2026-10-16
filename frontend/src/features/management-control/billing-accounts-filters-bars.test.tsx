import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import i18n from '@/i18n'
import { apiClient } from '@/api/client'
import { defaultAccountFlowsFilters, defaultBillingFilters } from '@/features/management-control/api'
import { AccountsFiltersBar } from '@/features/management-control/accounts-filters-bar'
import { BillingFiltersBar } from '@/features/management-control/billing-filters-bar'

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

describe('BillingFiltersBar', () => {
  it('AC-011 exposes period, scope and grouping by accessible role', () => {
    const filters = { ...defaultBillingFilters(), dateFrom: '2026-01-01', dateTo: '2026-06-30' }
    renderWithClient(<BillingFiltersBar filters={filters} onChange={vi.fn()} />)

    expect(screen.getByLabelText('Proforma from')).toHaveValue('2026-01-01')
    expect(screen.getByLabelText('to')).toHaveValue('2026-06-30')
    for (const name of ['Company', 'Company site', 'Operational site']) {
      expect(screen.getByRole('button', { name })).toBeInTheDocument()
    }
    expect(screen.getByRole('combobox', { name: 'Group the amount to invoice by' })).toHaveTextContent('Business function')
  })

  it('AC-011 a period change calls onChange with the updated filters', () => {
    const onChange = vi.fn()
    const filters = { ...defaultBillingFilters(), dateFrom: '2026-01-01', dateTo: '2026-06-30' }
    renderWithClient(<BillingFiltersBar filters={filters} onChange={onChange} />)

    fireEvent.change(screen.getByLabelText('Proforma from'), { target: { value: '2026-02-01' } })

    expect(onChange).toHaveBeenCalledWith({ ...filters, dateFrom: '2026-02-01' })
  })

  it('AC-011 the grouping offers the three dimensions and reports the choice', async () => {
    const onChange = vi.fn()
    const filters = defaultBillingFilters()
    renderWithClient(<BillingFiltersBar filters={filters} onChange={onChange} />)

    fireEvent.keyDown(screen.getByRole('combobox', { name: 'Group the amount to invoice by' }), { key: 'ArrowDown' })
    for (const name of ['Business function', 'Company', 'Sales representative']) {
      expect(await screen.findByRole('option', { name })).toBeInTheDocument()
    }
    fireEvent.click(screen.getByRole('option', { name: 'Sales representative' }))

    expect(onChange).toHaveBeenCalledWith({ ...filters, groupBy: 'commercial' })
  })
})

describe('AccountsFiltersBar', () => {
  it('AC-011 exposes the period and the scope by accessible role', () => {
    const filters = { ...defaultAccountFlowsFilters(), dateFrom: '2026-01-01', dateTo: '2026-06-30' }
    renderWithClient(<AccountsFiltersBar filters={filters} onChange={vi.fn()} />)

    expect(screen.getByLabelText('Collections and payments from')).toHaveValue('2026-01-01')
    expect(screen.getByLabelText('to')).toHaveValue('2026-06-30')
    for (const name of ['Company', 'Company site', 'Operational site']) {
      expect(screen.getByRole('button', { name })).toBeInTheDocument()
    }
  })

  it('AC-011 a period change calls onChange with the updated filters', () => {
    const onChange = vi.fn()
    const filters = { ...defaultAccountFlowsFilters(), dateFrom: '2026-01-01', dateTo: '2026-06-30' }
    renderWithClient(<AccountsFiltersBar filters={filters} onChange={onChange} />)

    fireEvent.change(screen.getByLabelText('to'), { target: { value: '2026-07-31' } })

    expect(onChange).toHaveBeenCalledWith({ ...filters, dateTo: '2026-07-31' })
  })
})
