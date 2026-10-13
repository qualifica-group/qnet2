import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { CommissionAdjustmentDialog } from '@/features/commission-settlements/commission-adjustment-dialog'

const apiMocks = vi.hoisted(() => ({ create: vi.fn(), update: vi.fn() }))

vi.mock('@/features/commission-settlements/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/commission-settlements/api')>()),
  createAdjustment: (...args: unknown[]) => apiMocks.create(...args),
  updateAdjustment: (...args: unknown[]) => apiMocks.update(...args),
}))
vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: () => Promise.resolve({ data: [], meta: { has_more: false } }),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  apiMocks.create.mockReset()
  apiMocks.update.mockReset()
})

describe('CommissionAdjustmentDialog (spec 0213 AC-022)', () => {
  it('standalone: offers the agent and company pickers and blocks submit without them', async () => {
    render(<CommissionAdjustmentDialog mode={{ type: 'standalone' }} onClose={vi.fn()} onSaved={vi.fn()} />, {
      wrapper: wrapper(),
    })

    expect(screen.getByRole('combobox', { name: 'Agent' })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Company' })).toBeInTheDocument()
    fireEvent.change(screen.getByLabelText(/^Amount/), { target: { value: '5' } })
    fireEvent.change(screen.getByLabelText(/^Notes/), { target: { value: 'x' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByText('The agent is required.')).toHaveAttribute('role', 'alert')
    expect(apiMocks.create).not.toHaveBeenCalled()
  })

  it('draft: fixes agent/company and sends commission_settlement_id', async () => {
    apiMocks.create.mockResolvedValue({})
    const onSaved = vi.fn()
    render(
      <CommissionAdjustmentDialog
        mode={{ type: 'draft', settlementId: 9, referent: { id: 5, name: 'Mario Rossi' }, company: { id: 2, name: 'Acme Srl' } }}
        onClose={vi.fn()}
        onSaved={onSaved}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
    fireEvent.change(screen.getByLabelText(/^Amount/), { target: { value: '-12.5' } })
    fireEvent.change(screen.getByLabelText(/^Date/), { target: { value: '2026-10-01' } })
    fireEvent.change(screen.getByLabelText(/^Notes/), { target: { value: 'Correction' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(apiMocks.create).toHaveBeenCalled())
    expect(apiMocks.create.mock.calls[0][0]).toEqual({
      referent_id: 5,
      company_id: 2,
      amount: -12.5,
      accrued_on: '2026-10-01',
      notes: 'Correction',
      commission_settlement_id: 9,
    })
    await waitFor(() => expect(onSaved).toHaveBeenCalled())
  })

  it('rejects a zero amount and missing notes with accessible errors', async () => {
    render(
      <CommissionAdjustmentDialog
        mode={{ type: 'draft', settlementId: 9, referent: { id: 5, name: 'M' }, company: { id: 2, name: 'A' } }}
        onClose={vi.fn()}
        onSaved={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Amount/), { target: { value: '0' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    const amount = screen.getByLabelText(/^Amount/)
    await waitFor(() => expect(amount).toHaveAttribute('aria-invalid', 'true'))
    expect(screen.getByText('The amount cannot be zero.')).toHaveAttribute('role', 'alert')
    expect(screen.getByText('The notes are required.')).toBeInTheDocument()
    expect(apiMocks.create).not.toHaveBeenCalled()
  })

  it('edit: prefills the movement and sends a PUT without parties', async () => {
    apiMocks.update.mockResolvedValue({})
    render(
      <CommissionAdjustmentDialog
        mode={{ type: 'edit', adjustmentId: 31, amount: '10.00', accruedOn: '2026-09-15', notes: 'Old' }}
        onClose={vi.fn()}
        onSaved={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Amount/), { target: { value: '12' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(apiMocks.update).toHaveBeenCalledWith(31, { amount: 12, accrued_on: '2026-09-15', notes: 'Old' }))
  })
})
