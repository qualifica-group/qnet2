import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { apiClient } from '@/api/client'
import { defaultIncomeStatementFilters } from '@/features/management-control/api'
import { IncomeStatementFiltersBar } from '@/features/management-control/income-statement-filters-bar'

vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn() } }))

const getMock = vi.mocked(apiClient.get)

const EMPTY_FOR_SELECT = {
  items: [],
  export_link: null,
  pagination: { total: 0, offset: 0, limit: 25, total_pages: 0, has_more: false },
}

function renderBar(onChange = vi.fn()) {
  const filters = { ...defaultIncomeStatementFilters(), dateFrom: '2026-01-01', dateTo: '2026-06-30' }
  // One client per test: a per-render client would reset the cache on every render.
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <IncomeStatementFiltersBar filters={filters} onChange={onChange} />
    </QueryClientProvider>,
  )
  return { onChange, filters }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  getMock.mockReset()
  getMock.mockResolvedValue({ data: EMPTY_FOR_SELECT })
})

describe('IncomeStatementFiltersBar', () => {
  it('AC-016 exposes every filter by accessible role', () => {
    renderBar()

    expect(screen.getByLabelText('Work orders starting from')).toHaveValue('2026-01-01')
    expect(screen.getByLabelText('to')).toHaveValue('2026-06-30')
    for (const name of ['Company', 'Company site', 'Operational site', 'Business function', 'Sales representative']) {
      expect(screen.getByRole('button', { name })).toBeInTheDocument()
    }
    expect(screen.getByRole('group', { name: 'Work order type' })).toBeInTheDocument()
    expect(screen.getByRole('checkbox', { name: 'Project' })).not.toBeChecked()
    expect(screen.getByRole('radiogroup', { name: 'Actual revenue basis' })).toBeInTheDocument()
    expect(screen.getByRole('radio', { name: 'Contract' })).toBeChecked()
    expect(screen.getByRole('combobox', { name: 'Segmentation' })).toHaveTextContent('None')
  })

  it('AC-016 a period change calls onChange with the updated filters', () => {
    const { onChange, filters } = renderBar()

    fireEvent.change(screen.getByLabelText('to'), { target: { value: '2026-12-31' } })

    expect(onChange).toHaveBeenCalledWith({ ...filters, dateTo: '2026-12-31' })
  })

  it('AC-016 revenue basis and segmentation changes call onChange', async () => {
    const { onChange, filters } = renderBar()

    fireEvent.click(screen.getByRole('radio', { name: 'Collected' }))
    expect(onChange).toHaveBeenLastCalledWith({ ...filters, revenueBasis: 'collected' })

    fireEvent.keyDown(screen.getByRole('combobox', { name: 'Segmentation' }), { key: 'ArrowDown' })
    fireEvent.click(await screen.findByRole('option', { name: 'Sales representative' }))
    expect(onChange).toHaveBeenLastCalledWith({ ...filters, segmentBy: 'commercial' })
  })

  it('AC-016 toggling a work order type calls onChange with the type list', () => {
    const { onChange, filters } = renderBar()

    fireEvent.click(screen.getByRole('checkbox', { name: 'Project' }))

    expect(onChange).toHaveBeenCalledWith({ ...filters, workOrderTypes: ['project'] })
  })
})
