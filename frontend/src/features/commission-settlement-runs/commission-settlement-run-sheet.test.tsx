import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { CommissionSettlementRunSheet } from '@/features/commission-settlement-runs/commission-settlement-run-sheet'
import { CommissionSettlementRunDetailSheet } from '@/features/commission-settlement-runs/commission-settlement-run-detail-sheet'
import type { CommissionSettlementRunResult } from '@/features/commission-settlement-runs/types'

const apiMocks = vi.hoisted(() => ({ create: vi.fn(), fetch: vi.fn() }))

vi.mock('@/features/commission-settlement-runs/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/commission-settlement-runs/api')>()),
  createCommissionSettlementRun: (...args: unknown[]) => apiMocks.create(...args),
  fetchCommissionSettlementRun: (...args: unknown[]) => apiMocks.fetch(...args),
}))

vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: () => Promise.resolve({ data: [], meta: { has_more: false } }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const RESULT: CommissionSettlementRunResult = {
  run: {
    id: 4,
    period_from: '2026-09-01',
    period_to: '2026-09-30',
    filters: null,
    min_amount: '0.00',
    settlements_count: 1,
    created_at: '2026-10-13T08:00:00Z',
    created_by: { id: 1, name: 'Admin' },
  },
  created: [
    {
      settlement_id: 12,
      referent: { id: 5, name: 'Mario Rossi' },
      company: { id: 2, name: 'Acme Srl' },
      taxable_amount: '1000.00',
      net_payable: '1020.00',
    },
  ],
  skipped: [
    { referent: { id: 6, name: 'Luca Bianchi' }, company: { id: 2, name: 'Acme Srl' }, reason: 'missing_profile', taxable_amount: '300.00' },
    { referent: { id: 7, name: 'Anna Verdi' }, company: { id: 2, name: 'Acme Srl' }, reason: 'below_threshold', taxable_amount: '10.00' },
  ],
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <MemoryRouter>{children}</MemoryRouter>
      </ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  apiMocks.create.mockReset()
  apiMocks.fetch.mockReset()
})

const openRow = (label: string) =>
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label }) }))
const applyRow = () => fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.apply') }))
const launch = () => fireEvent.click(screen.getAllByRole('button', { name: 'Run' })[0])

function setPeriod(from: string, to: string) {
  openRow('Period')
  fireEvent.change(screen.getByLabelText(/^Period from/), { target: { value: from } })
  fireEvent.change(screen.getByLabelText(/^Period to/), { target: { value: to } })
  applyRow()
}

// REQUIREMENT CHANGED (spec 0265 D-4): "Nuovo lancio" is a record draft in a side sheet, rows closed until opened.
describe('CommissionSettlementRunSheet (spec 0213 AC-020, spec 0265 D-4)', () => {
  it('sends the contract body and then shows the run it created', async () => {
    apiMocks.create.mockResolvedValue(RESULT)
    const onLaunched = vi.fn()
    render(<CommissionSettlementRunSheet open onClose={vi.fn()} onLaunched={onLaunched} />, { wrapper: wrapper() })

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    setPeriod('2026-09-01', '2026-09-30')
    openRow('Minimum threshold (€)')
    fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '25' } })
    applyRow()
    openRow('Roles')
    fireEvent.click(screen.getByRole('checkbox', { name: 'Commercial' }))
    applyRow()
    launch()

    await waitFor(() => expect(apiMocks.create).toHaveBeenCalled())
    expect(apiMocks.create.mock.calls[0][0]).toEqual({
      period_from: '2026-09-01',
      period_to: '2026-09-30',
      company_id: null,
      referent_ids: null,
      recipient_roles: ['COMMERCIAL'],
      min_amount: 25,
    })
    expect(await screen.findByText('Run by Admin', { exact: false })).toBeInTheDocument()
    expect(onLaunched).toHaveBeenCalled()
    expect(screen.getByRole('link', { name: 'Open the draft of Mario Rossi - Acme Srl' })).toHaveAttribute(
      'href',
      '/commission-settlements/12',
    )
    expect(screen.getByText('No valid fiscal profile at the end date')).toBeInTheDocument()
    expect(screen.getByText('Amount below the minimum threshold: the accrual stays open')).toBeInTheDocument()
  })

  it('does not call the API when the end precedes the start and shows it on the period row', async () => {
    render(<CommissionSettlementRunSheet open onClose={vi.fn()} onLaunched={vi.fn()} />, { wrapper: wrapper() })

    setPeriod('2026-09-10', '2026-09-01')
    launch()

    expect(await screen.findByText('The end date cannot be before the start date.')).toHaveAttribute('role', 'alert')
    expect(apiMocks.create).not.toHaveBeenCalled()
  })

  it('asks before leaving a changed draft', async () => {
    const onClose = vi.fn()
    render(<CommissionSettlementRunSheet open onClose={onClose} onLaunched={vi.fn()} />, { wrapper: wrapper() })

    setPeriod('2026-08-01', '2026-08-31')
    fireEvent.click(screen.getAllByRole('button', { name: 'Cancel' })[0])

    expect(await screen.findByText('Leave without running?')).toBeInTheDocument()
    expect(onClose).not.toHaveBeenCalled()
  })
})

// REQUIREMENT CHANGED (spec 0265 D-3): a past run opens as a record in a side sheet, no longer a dialog.
describe('CommissionSettlementRunDetailSheet', () => {
  it('loads a past run and shows it as a record', async () => {
    apiMocks.fetch.mockResolvedValue(RESULT)
    render(<CommissionSettlementRunDetailSheet runId={4} onClose={vi.fn()} />, { wrapper: wrapper() })

    expect(await screen.findByText('Luca Bianchi')).toBeInTheDocument()
    expect(screen.getByText(/^Run by /)).toBeInTheDocument()
    expect(apiMocks.fetch).toHaveBeenCalledWith(4)
  })
})
