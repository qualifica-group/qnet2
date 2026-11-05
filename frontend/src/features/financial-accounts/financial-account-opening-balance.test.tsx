import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { FinancialAccountOpeningBalance } from '@/features/financial-accounts/financial-account-opening-balance'

const updateMock = vi.fn()
vi.mock('@/features/financial-accounts/api', () => ({
  updateOpeningBalance: (...args: unknown[]) => updateMock(...args),
}))
const permissions = new Set<string>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (name: string) => permissions.has(name),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

function renderSection(currentBalance: string | null) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <FinancialAccountOpeningBalance
          accountId={7}
          openingBalance="1000.00"
          openingBalanceDate="2026-01-01"
          currentBalance={currentBalance}
        />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateMock.mockReset()
  permissions.clear()
})

describe('FinancialAccountOpeningBalance (AC-022)', () => {
  it('is read-only without manageOpeningBalance and hides the current balance when null', () => {
    renderSection(null)

    expect(screen.getByText('Opening date').nextElementSibling).toHaveTextContent('01/01/2026')
    expect(screen.getByText('Amount').nextElementSibling).toHaveTextContent(/1.?000,00/)
    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByText('Current balance')).not.toBeInTheDocument()
  })

  it('shows the current balance with a link to the statement', () => {
    renderSection('1350.00')

    expect(screen.getByRole('link', { name: 'View statement' })).toHaveAttribute(
      'href',
      '/account-statements?account=7',
    )
    expect(screen.getByText('Current balance').nextElementSibling).toHaveTextContent(/1.?350,00/)
  })

  it('edits through the modal and sends the normalized PATCH payload', async () => {
    permissions.add('financial-accounts.manageOpeningBalance')
    updateMock.mockResolvedValue({})
    renderSection(null)

    fireEvent.click(screen.getByRole('button', { name: 'Edit' }))
    fireEvent.change(await screen.findByRole('textbox', { name: 'Amount' }), { target: { value: '2500,5' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(updateMock).toHaveBeenCalledWith(7, { opening_balance: '2500.5', opening_balance_date: '2026-01-01' }),
    )
  })

  it('announces an invalid amount with the aria triad and does not submit', async () => {
    permissions.add('financial-accounts.manageOpeningBalance')
    renderSection(null)

    fireEvent.click(screen.getByRole('button', { name: 'Edit' }))
    const amount = await screen.findByRole('textbox', { name: 'Amount' })
    fireEvent.change(amount, { target: { value: 'abc' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('valid amount')
    expect(amount).toHaveAttribute('aria-invalid', 'true')
    expect(updateMock).not.toHaveBeenCalled()
  })
})
