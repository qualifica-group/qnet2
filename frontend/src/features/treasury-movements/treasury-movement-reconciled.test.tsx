import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { TreasuryMovementDialog } from '@/features/treasury-movements/treasury-movement-dialog'
import type { TreasuryMovement } from '@/features/treasury-movements/types'

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/attachments/documents-section', () => ({ DocumentsSection: () => null }))
vi.mock('@/components/ui/async-paginated-select', () => ({ AsyncPaginatedSelect: () => <div /> }))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const movementMock = vi.hoisted(() => vi.fn())
vi.mock('@/features/treasury-movements/api', () => ({
  createTreasuryMovement: vi.fn(),
  updateTreasuryMovement: vi.fn(),
  fetchTreasuryMovement: (id: number) => movementMock(id),
  fetchSubAccountOptions: () => Promise.resolve([]),
}))
vi.mock('@/features/financial-accounts/api', () => ({ fetchFinancialAccount: vi.fn() }))

// Spec 0363 - AC-016.

function movement(reconciled: boolean): TreasuryMovement {
  return {
    id: 5,
    type: 'expense',
    direction: 'out',
    movement_date: '2026-11-03',
    amount: '150.00',
    account: { id: 5, name: 'Intesa CC', type: 'bank_account', company: null },
    counterpart_account: null,
    description: 'Cancelleria',
    sub_account: { kind: 'cost', id: 1, code: '60.01', name: 'Cancelleria' },
    registry: null,
    work_order: null,
    attachments_count: 0,
    reconciled,
    created_by: null,
    created_at: '2026-11-03T10:00:00Z',
    updated_at: '2026-11-03T10:00:00Z',
  }
}

function renderDialog() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <TreasuryMovementDialog target={{ id: 5 }} onClose={vi.fn()} onSaved={vi.fn()} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('TreasuryMovementDialog with a reconciled movement (AC-016)', () => {
  it('opens read-only with the notice and cannot be saved', async () => {
    movementMock.mockResolvedValue(movement(true))
    renderDialog()

    expect(await screen.findByRole('status')).toHaveTextContent('ticked on a bank statement')
    expect(screen.getByRole('textbox', { name: /Description/ })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled()
  })

  it('stays editable when the movement is not reconciled', async () => {
    movementMock.mockResolvedValue(movement(false))
    renderDialog()

    expect(await screen.findByRole('textbox', { name: /Description/ })).toBeEnabled()
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled()
  })
})
