import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { FinancialAccountDetailView } from '@/features/financial-accounts/financial-account-detail'
import type { FinancialAccountDetailWithPermissions } from '@/features/financial-accounts/types'

const revealMock = vi.fn()
vi.mock('@/features/financial-accounts/api', () => ({
  revealCardNumber: (...args: unknown[]) => revealMock(...args),
}))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => <div>activity-log-section</div>,
}))

function account(revealAllowed: boolean): FinancialAccountDetailWithPermissions {
  return {
    id: 7,
    type: 'card',
    name: 'Intesa',
    company: null,
    iban: null,
    account_number: null,
    address_line: null,
    postal_code: null,
    country: null,
    state: null,
    province: null,
    city: null,
    card_type: 'credit',
    card_circuit: 'visa',
    linked_account: { id: 3, name: 'Intesa CC', iban: null },
    card_holder: 'Mario Rossi',
    card_number_masked: '**** 1111',
    card_expiry: '12/2030',
    notes: null,
    created_at: '2026-01-01T09:00:00Z',
    updated_at: '2026-01-01T09:00:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: false },
      fields: {},
      actions: { view_activity: false, reveal_card_number: revealAllowed },
    },
  }
}

function renderDetail(revealAllowed: boolean) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
  return render(<FinancialAccountDetailView financialAccount={account(revealAllowed)} />, {
    wrapper,
  })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  revealMock.mockReset()
})

describe('FinancialAccountDetailView (AC-023)', () => {
  it('shows the masked number and hides the reveal button without the permission', () => {
    renderDetail(false)

    expect(screen.getByText('**** 1111')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Show number' })).not.toBeInTheDocument()
  })

  it('reveals the full number through the endpoint when permitted', async () => {
    revealMock.mockResolvedValue('4111111111111111')
    renderDetail(true)

    fireEvent.click(screen.getByRole('button', { name: 'Show number' }))

    expect(await screen.findByText('4111111111111111')).toBeInTheDocument()
    expect(revealMock).toHaveBeenCalledWith(7)
    await waitFor(() => expect(screen.queryByText('**** 1111')).not.toBeInTheDocument())
  })
})
