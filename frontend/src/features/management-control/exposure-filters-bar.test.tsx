import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { apiClient } from '@/api/client'
import { defaultExposureFilters } from '@/features/management-control/api'
import { ExposureFiltersBar } from '@/features/management-control/exposure-filters-bar'

vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn() } }))

const getMock = vi.mocked(apiClient.get)

const EMPTY_FOR_SELECT = {
  items: [],
  export_link: null,
  pagination: { total: 0, offset: 0, limit: 25, total_pages: 0, has_more: false },
}

function renderBar(onChange = vi.fn()) {
  const filters = { ...defaultExposureFilters(), dateFrom: '2026-01-01', dateTo: '2026-06-30' }
  // One client per test: a per-render client would reset the cache on every render.
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <ExposureFiltersBar filters={filters} onChange={onChange} />
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

describe('ExposureFiltersBar', () => {
  it('AC-016 exposes every filter by accessible role', () => {
    renderBar()

    expect(screen.getByLabelText('Flows from')).toHaveValue('2026-01-01')
    expect(screen.getByLabelText('to')).toHaveValue('2026-06-30')
    for (const name of ['Company', 'Company site', 'Operational site']) {
      expect(screen.getByRole('button', { name })).toBeInTheDocument()
    }
    expect(screen.getByRole('radiogroup', { name: 'Document type' })).toBeInTheDocument()
    expect(screen.getByRole('radio', { name: 'Invoices' })).toBeChecked()
    expect(screen.getByRole('combobox', { name: 'Exposure by' })).toHaveTextContent('Company')
    expect(screen.getByRole('combobox', { name: 'Top by overdue' })).toHaveTextContent('Top 10')
  })

  it('AC-016 a period change calls onChange with the updated filters', () => {
    const { onChange, filters } = renderBar()

    fireEvent.change(screen.getByLabelText('Flows from'), { target: { value: '2026-02-01' } })

    expect(onChange).toHaveBeenCalledWith({ ...filters, dateFrom: '2026-02-01' })
  })

  it('AC-016 document type and grouping changes call onChange', async () => {
    const { onChange, filters } = renderBar()

    fireEvent.click(screen.getByRole('radio', { name: 'Proforma' }))
    expect(onChange).toHaveBeenLastCalledWith({ ...filters, documentType: 'proforma' })

    fireEvent.keyDown(screen.getByRole('combobox', { name: 'Exposure by' }), { key: 'ArrowDown' })
    fireEvent.click(await screen.findByRole('option', { name: 'Company site' }))
    expect(onChange).toHaveBeenLastCalledWith({ ...filters, groupBy: 'company_site' })
  })

  it('AC-016 the top selector offers 5, 10 and 25 and reports the choice', async () => {
    const { onChange, filters } = renderBar()

    fireEvent.keyDown(screen.getByRole('combobox', { name: 'Top by overdue' }), { key: 'ArrowDown' })
    expect(await screen.findByRole('option', { name: 'Top 5' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Top 10' })).toBeInTheDocument()
    fireEvent.click(screen.getByRole('option', { name: 'Top 25' }))

    expect(onChange).toHaveBeenCalledWith({ ...filters, top: 25 })
  })
})
