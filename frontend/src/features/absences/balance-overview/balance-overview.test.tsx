import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import '@/i18n'
import { BalanceOverview } from '@/features/absences/balance-overview/balance-overview'
import type { AbsenceBalanceOverviewPage, AbsenceBalanceRow } from '@/features/absences/types'

const fetchOverviewMock = vi.fn()
const bulkMock = vi.fn()
const exportMock = vi.fn()
vi.mock('@/features/absences/api', () => ({
  fetchAbsenceBalanceOverview: (params: unknown) => fetchOverviewMock(params),
  bulkSaveAbsenceAllowances: (payload: unknown) => bulkMock(payload),
  exportAbsenceBalanceOverview: (filters: unknown, format: unknown) => exportMock(filters, format),
}))

const toastSuccess = vi.fn()
vi.mock('sonner', () => ({ toast: { success: (message: string) => toastSuccess(message), error: vi.fn() } }))

const saveBlobMock = vi.fn()
vi.mock('@/lib/download', () => ({ saveBlob: (blob: Blob, name: string) => saveBlobMock(blob, name) }))

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ resource, onChange }: { resource: string; onChange: (id: number | null) => void }) => (
    <button type="button" onClick={() => onChange(3)}>
      {`pick-${resource}`}
    </button>
  ),
}))

function row(overrides: Partial<AbsenceBalanceRow> = {}): AbsenceBalanceRow {
  return {
    absence_type: { id: 1, name: 'Ferie', color: 'blue' },
    source: 'allowance',
    annual_minutes: 9600,
    carried_over_minutes: 0,
    accrued_minutes: 5000,
    used_minutes: 600,
    pending_minutes: 120,
    residual_minutes: 960,
    ...overrides,
  }
}

const PAGE: AbsenceBalanceOverviewPage = {
  data: {
    year: new Date().getFullYear(),
    types: [{ id: 1, name: 'Ferie', color: 'blue' }],
    people: [
      { user: { id: 7, name: 'Anna Rossi' }, daily_minutes: 480, balances: [row()] },
      { user: { id: 8, name: 'Luca Bianchi' }, daily_minutes: 240, balances: [row({ residual_minutes: -240 })] },
    ],
  },
  meta: { current_page: 1, last_page: 2, total: 30 },
}

function renderOverview() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <BalanceOverview />
    </QueryClientProvider>,
  )
}

describe('BalanceOverview', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchOverviewMock.mockResolvedValue(PAGE)
  })

  it('shows the residual of each person in days + hours with the breakdown for assistive tech', async () => {
    renderOverview()

    expect(await screen.findByRole('rowheader', { name: /Anna Rossi/ })).toBeInTheDocument()
    expect(screen.getByText('2 g')).toBeInTheDocument()
    expect(screen.getByText('-1 g')).toBeInTheDocument()
    expect(
      screen.getAllByText('Spettanza 20 g, maturato 10 g 3 h 20 min, goduto 1 g 2 h, in attesa 2 h').length,
    ).toBeGreaterThan(0)
  })

  it('refetches with the filter and back on page 1 when a filter changes, and pages forward', async () => {
    renderOverview()
    await screen.findByRole('rowheader', { name: /Anna Rossi/ })

    fireEvent.click(screen.getByRole('button', { name: 'Successiva' }))
    await waitFor(() => expect(fetchOverviewMock).toHaveBeenLastCalledWith(expect.objectContaining({ page: 2 })))

    fireEvent.click(screen.getByRole('button', { name: 'pick-business-functions' }))
    await waitFor(() =>
      expect(fetchOverviewMock).toHaveBeenLastCalledWith(expect.objectContaining({ page: 1, business_function_id: 3 })),
    )

    fireEvent.click(screen.getByRole('button', { name: 'pick-operational-sites' }))
    await waitFor(() =>
      expect(fetchOverviewMock).toHaveBeenLastCalledWith(expect.objectContaining({ operational_site_id: 3 })),
    )

    fireEvent.change(screen.getByRole('searchbox', { name: 'Cerca una persona' }), { target: { value: 'anna' } })
    await waitFor(() => expect(fetchOverviewMock).toHaveBeenLastCalledWith(expect.objectContaining({ search: 'anna' })))
  })

  it('sets the allowance in days for the selected people and toasts how many rows changed', async () => {
    bulkMock.mockResolvedValue({ updated: 2 })
    renderOverview()
    await screen.findByRole('rowheader', { name: /Anna Rossi/ })

    expect(screen.getByRole('button', { name: /Imposta spettanza/ })).toBeDisabled()
    fireEvent.click(screen.getByRole('checkbox', { name: 'Seleziona tutte le persone della pagina' }))
    fireEvent.click(screen.getByRole('button', { name: 'Imposta spettanza (2)' }))

    const dialog = await screen.findByRole('dialog')
    fireEvent.change(within(dialog).getByLabelText('Spettanza annua (giorni)'), { target: { value: '22' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Salva' }))

    await waitFor(() =>
      expect(bulkMock).toHaveBeenCalledWith({
        absence_type_id: 1,
        year: PAGE.data.year,
        user_ids: [7, 8],
        annual_days: 22,
      }),
    )
    await waitFor(() => expect(toastSuccess).toHaveBeenCalledWith('2 spettanze aggiornate.'))
  })

  it('sends annual_days null to go back to the type default', async () => {
    bulkMock.mockResolvedValue({ updated: 1 })
    renderOverview()
    await screen.findByRole('rowheader', { name: /Anna Rossi/ })

    fireEvent.click(screen.getByRole('checkbox', { name: 'Seleziona Anna Rossi' }))
    fireEvent.click(screen.getByRole('button', { name: 'Imposta spettanza (1)' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.click(within(dialog).getByRole('checkbox', { name: 'Usa il default del tipo' }))
    fireEvent.click(within(dialog).getByRole('button', { name: 'Salva' }))

    await waitFor(() => expect(bulkMock).toHaveBeenCalledWith(expect.objectContaining({ user_ids: [7], annual_days: null })))
  })

  it('exports with the current filters and the chosen format', async () => {
    exportMock.mockResolvedValue({ blob: new Blob(['x']), filename: 'saldi.xlsx' })
    renderOverview()
    await screen.findByRole('rowheader', { name: /Anna Rossi/ })

    fireEvent.click(screen.getByRole('button', { name: 'Esporta XLSX' }))

    await waitFor(() => expect(exportMock).toHaveBeenCalledWith({ year: PAGE.data.year }, 'xlsx'))
    await waitFor(() => expect(saveBlobMock).toHaveBeenCalledWith(expect.any(Blob), 'saldi.xlsx'))
  })

  it('shows the empty state when no person matches', async () => {
    fetchOverviewMock.mockResolvedValue({ ...PAGE, data: { ...PAGE.data, people: [] }, meta: { current_page: 1, last_page: 1, total: 0 } })
    renderOverview()

    expect(await screen.findByText('Nessuna persona corrisponde ai filtri.')).toBeInTheDocument()
  })
})
