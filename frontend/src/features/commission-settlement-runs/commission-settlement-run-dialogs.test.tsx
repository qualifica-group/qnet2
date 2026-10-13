import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import {
  CommissionSettlementRunDetailDialog,
  CommissionSettlementRunDialog,
} from '@/features/commission-settlement-runs/commission-settlement-run-dialogs'
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
      <MemoryRouter>{children}</MemoryRouter>
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

describe('CommissionSettlementRunDialog (spec 0213 AC-020)', () => {
  it('sends the contract body and shows created and skipped with the translated reason', async () => {
    apiMocks.create.mockResolvedValue(RESULT)
    const onLaunched = vi.fn()
    render(<CommissionSettlementRunDialog open onClose={vi.fn()} onLaunched={onLaunched} />, { wrapper: wrapper() })

    fireEvent.change(screen.getByLabelText(/^Period from/), { target: { value: '2026-09-01' } })
    fireEvent.change(screen.getByLabelText(/^Period to/), { target: { value: '2026-09-30' } })
    fireEvent.change(screen.getByLabelText(/^Minimum threshold/), { target: { value: '25' } })
    fireEvent.click(screen.getByRole('checkbox', { name: 'Commercial' }))
    fireEvent.click(screen.getByRole('button', { name: 'Run' }))

    await waitFor(() => expect(apiMocks.create).toHaveBeenCalled())
    expect(apiMocks.create.mock.calls[0][0]).toEqual({
      period_from: '2026-09-01',
      period_to: '2026-09-30',
      company_id: null,
      referent_ids: null,
      recipient_roles: ['COMMERCIAL'],
      min_amount: 25,
    })
    expect(await screen.findByText('Run outcome')).toBeInTheDocument()
    expect(onLaunched).toHaveBeenCalled()
    expect(screen.getByRole('link', { name: 'Open the draft of Mario Rossi - Acme Srl' })).toHaveAttribute(
      'href',
      '/commission-settlements/12',
    )
    expect(screen.getByText('No valid fiscal profile at the end date')).toBeInTheDocument()
    expect(screen.getByText('Amount below the minimum threshold: the accrual stays open')).toBeInTheDocument()
  })

  it('does not call the API when period_to precedes period_from and flags the field accessibly', async () => {
    render(<CommissionSettlementRunDialog open onClose={vi.fn()} onLaunched={vi.fn()} />, { wrapper: wrapper() })

    fireEvent.change(screen.getByLabelText(/^Period from/), { target: { value: '2026-09-10' } })
    fireEvent.change(screen.getByLabelText(/^Period to/), { target: { value: '2026-09-01' } })
    fireEvent.click(screen.getByRole('button', { name: 'Run' }))

    const periodTo = screen.getByLabelText(/^Period to/)
    await waitFor(() => expect(periodTo).toHaveAttribute('aria-invalid', 'true'))
    const message = screen.getByText('The end date cannot be before the start date.')
    expect(message).toHaveAttribute('role', 'alert')
    expect(periodTo.getAttribute('aria-describedby')).toContain(message.id)
    expect(apiMocks.create).not.toHaveBeenCalled()
  })
})

describe('CommissionSettlementRunDetailDialog', () => {
  it('loads and shows the outcome of a past run', async () => {
    apiMocks.fetch.mockResolvedValue(RESULT)
    render(<CommissionSettlementRunDetailDialog runId={4} onClose={vi.fn()} />, { wrapper: wrapper() })

    expect(await screen.findByText('Luca Bianchi')).toBeInTheDocument()
    expect(apiMocks.fetch).toHaveBeenCalledWith(4)
  })
})
