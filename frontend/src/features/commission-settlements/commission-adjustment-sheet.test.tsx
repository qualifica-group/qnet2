import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { CommissionAdjustmentSheet } from '@/features/commission-settlements/commission-adjustment-sheet'

// The pickers render the quick-create "+" (spec 0028), gated by `<Can>`: no abilities here, so it stays hidden.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

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
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  apiMocks.create.mockReset()
  apiMocks.update.mockReset()
})

const openRow = (label: string) =>
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label }) }))
const applyRow = () => fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.apply') }))
const save = () => fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

/** One row open at a time (spec 0265 D-2): open it, type, confirm with Done. */
function enter(label: string, value: string, field: () => HTMLElement) {
  openRow(label)
  fireEvent.change(field(), { target: { value } })
  applyRow()
}

const amountInput = () => screen.getByRole('spinbutton')
const textInput = () => screen.getByRole('textbox')
const dateInput = () => screen.getByLabelText(/^Date/)

const DRAFT_MODE = { type: 'draft', settlementId: 9, referent: { id: 5, name: 'Mario Rossi' }, company: { id: 2, name: 'Acme Srl' } } as const

// REQUIREMENT CHANGED (spec 0265 D-2): the adjustment is a record draft in a side sheet, rows closed until opened.
describe('CommissionAdjustmentSheet (spec 0213 AC-022, spec 0265 D-2)', () => {
  it('stays closed without a mode', () => {
    render(<CommissionAdjustmentSheet mode={null} onClose={vi.fn()} onSaved={vi.fn()} />, { wrapper: wrapper() })

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('standalone: the agent and company rows are required before saving', async () => {
    render(<CommissionAdjustmentSheet mode={{ type: 'standalone' }} onClose={vi.fn()} onSaved={vi.fn()} />, {
      wrapper: wrapper(),
    })

    openRow('Agent')
    expect(screen.getByRole('combobox', { name: 'Agent' })).toBeInTheDocument()
    applyRow()
    enter('Amount (€)', '5', amountInput)
    enter('Notes', 'x', textInput)
    save()

    expect(await screen.findByText('The agent is required.')).toHaveAttribute('role', 'alert')
    expect(screen.getByText('The company is required.')).toBeInTheDocument()
    expect(apiMocks.create).not.toHaveBeenCalled()
  })

  it('draft: shows the statement agent/company and sends commission_settlement_id', async () => {
    apiMocks.create.mockResolvedValue({})
    const onSaved = vi.fn()
    render(<CommissionAdjustmentSheet mode={DRAFT_MODE} onClose={vi.fn()} onSaved={onSaved} />, { wrapper: wrapper() })

    expect(screen.getByText('Mario Rossi')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Agent' }) })).not.toBeInTheDocument()
    enter('Amount (€)', '-12.5', amountInput)
    enter('Date', '2026-10-01', dateInput)
    enter('Notes', 'Correction', textInput)
    save()

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

  it('shows a zero amount and missing notes on their closed rows', async () => {
    render(<CommissionAdjustmentSheet mode={DRAFT_MODE} onClose={vi.fn()} onSaved={vi.fn()} />, { wrapper: wrapper() })

    enter('Amount (€)', '0', amountInput)
    save()

    expect(await screen.findByText('The amount cannot be zero.')).toHaveAttribute('role', 'alert')
    expect(screen.getByText('The notes are required.')).toBeInTheDocument()
    expect(apiMocks.create).not.toHaveBeenCalled()
  })

  it('edit: prefills the movement and sends a PUT without parties', async () => {
    apiMocks.update.mockResolvedValue({})
    render(
      <CommissionAdjustmentSheet
        mode={{ type: 'edit', adjustmentId: 31, amount: '10.00', accruedOn: '2026-09-15', notes: 'Old' }}
        onClose={vi.fn()}
        onSaved={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByText('Old')).toBeInTheDocument()
    enter('Amount (€)', '12', amountInput)
    save()

    await waitFor(() => expect(apiMocks.update).toHaveBeenCalledWith(31, { amount: 12, accrued_on: '2026-09-15', notes: 'Old' }))
  })

  it('asks before leaving a draft with unsaved data', async () => {
    const onClose = vi.fn()
    render(<CommissionAdjustmentSheet mode={DRAFT_MODE} onClose={onClose} onSaved={vi.fn()} />, { wrapper: wrapper() })

    enter('Notes', 'typed', textInput)
    fireEvent.click(screen.getAllByRole('button', { name: 'Cancel' })[0])

    expect(await screen.findByText('Leave without saving?')).toBeInTheDocument()
    expect(onClose).not.toHaveBeenCalled()
  })

  it('leaves at once when nothing was typed', () => {
    const onClose = vi.fn()
    render(<CommissionAdjustmentSheet mode={DRAFT_MODE} onClose={onClose} onSaved={vi.fn()} />, { wrapper: wrapper() })

    fireEvent.click(screen.getAllByRole('button', { name: 'Cancel' })[0])

    expect(onClose).toHaveBeenCalled()
  })
})
