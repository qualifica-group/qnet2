import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter, useLocation } from 'react-router-dom'
import i18n from '@/i18n'
import { apiClient } from '@/api/client'
import LedgerPage from '@/pages/ledger-page'
import { makeLedger } from '@/features/ledger/ledger-fixtures'

vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn() } }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/components/page-header', () => ({ PageHeader: () => null }))

const getMock = vi.mocked(apiClient.get)

const REGISTRIES_PAGE = {
  items: [{ id: 7, label: 'Rossi Srl' }],
  export_link: null,
  pagination: { total: 1, offset: 0, limit: 25, total_pages: 1, has_more: false },
}
const EMPTY_PAGE = { ...REGISTRIES_PAGE, items: [], pagination: { ...REGISTRIES_PAGE.pagination, total: 0 } }

function LocationProbe() {
  const location = useLocation()
  return <output aria-label="location">{location.search}</output>
}

function renderPage(entry: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[entry]}>
        <LedgerPage />
        <LocationProbe />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  getMock.mockReset()
  getMock.mockImplementation((url: string) => {
    if (url.endsWith('/ledger')) {
      return Promise.resolve({ data: { success: true, message: 'OK', data: makeLedger() } })
    }
    return Promise.resolve({ data: url.startsWith('/registries') ? REGISTRIES_PAGE : EMPTY_PAGE })
  })
})

describe('LedgerPage', () => {
  it('AC-015 without a registry shows the empty state and the selector, no filters', () => {
    renderPage('/ledger')

    expect(screen.getByText('Choose a registry to see its ledger.')).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Registry' })).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'Ledger filters' })).not.toBeInTheDocument()
  })

  it('AC-015 picking a registry writes ?registry=id and shows filters, summary and sections', async () => {
    renderPage('/ledger')

    fireEvent.click(screen.getByRole('combobox', { name: 'Registry' }))
    fireEvent.click(await screen.findByRole('option', { name: /Rossi Srl/ }))

    expect(screen.getByLabelText('location')).toHaveTextContent('?registry=7')
    expect(await screen.findByRole('region', { name: 'Ledger filters' })).toBeInTheDocument()
    expect(await screen.findByRole('heading', { name: 'Customer' })).toBeInTheDocument()
    expect(screen.getByRole('radio', { name: 'Invoices' })).toBeChecked()
    expect(screen.getByText('Invoiced')).toBeInTheDocument()
    expect(screen.queryByText('Choose a registry to see its ledger.')).not.toBeInTheDocument()
  })

  it('AC-015 reads the registry from the URL', async () => {
    renderPage('/ledger?registry=7')

    expect(await screen.findByRole('heading', { name: 'Customer' })).toBeInTheDocument()
    expect(getMock.mock.calls.some(([url]) => url === '/registries/7/ledger')).toBe(true)
  })
})
