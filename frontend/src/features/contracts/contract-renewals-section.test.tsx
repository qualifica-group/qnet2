import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ConfirmContext, type ConfirmFn } from '@/components/confirm-dialog-context'
import i18n from '@/i18n'
import { formatDate } from '@/lib/formatting/date-display'
import { contracts as contractsEn } from '@/i18n/locales/en-contracts'
import { ContractRenewalsSection } from '@/features/contracts/contract-renewals-section'
import { undoContractRenewal } from '@/features/contracts/api'
import { contractWithPermissions, renewalFixture } from '@/features/contracts/contract-renewal-fixtures'
import type { ContractDetailWithPermissions } from '@/features/contracts/types'

/** Spec 0358 AC-028: history rows, undo only on the latest renewal, confirmation, DELETE. */

vi.mock('@/features/contracts/api', () => ({
  undoContractRenewal: vi.fn(),
  contractDetailQueryKey: (id: number | null) => ['contracts', 'detail', id],
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const TWO_RENEWALS = [
  renewalFixture({ id: 1, number: 1, can_undo: false, work_orders_count: 3 }),
  renewalFixture({
    id: 2,
    number: 2,
    starts_on: '2028-01-01',
    expiry_date: '2028-12-31',
    renewal_date: null,
    previous_expiry_date: '2027-12-31',
    previous_renewal_date: '2027-11-30',
    work_orders_count: 0,
    can_undo: true,
  }),
]

function renderSection(contract: ContractDetailWithPermissions, confirm: ConfirmFn = vi.fn<ConfirmFn>().mockResolvedValue(true)) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const onChanged = vi.fn()
  render(
    <QueryClientProvider client={client}>
      <ConfirmContext.Provider value={confirm}>
        <ContractRenewalsSection contract={contract} onChanged={onChanged} />
      </ConfirmContext.Provider>
    </QueryClientProvider>,
  )
  return { onChanged, confirm }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { contracts: contractsEn }, true, true)
})

beforeEach(() => {
  vi.mocked(undoContractRenewal).mockReset()
})

describe('ContractRenewalsSection', () => {
  it('always shows the Initial row, even when the contract was never renewed', () => {
    renderSection(contractWithPermissions({ renew: true }, { initial_period_work_orders_count: 4 }))

    const rows = screen.getAllByRole('listitem')
    expect(rows).toHaveLength(1)
    expect(within(rows[0]).getByText('Initial')).toBeInTheDocument()
    expect(within(rows[0]).getByText('4')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Undo renewal' })).not.toBeInTheDocument()
  })

  it('shows one row per renewal with its dates and work orders count', () => {
    renderSection(contractWithPermissions({ renew: true }, { renewals: TWO_RENEWALS, renewal_number: 2 }))

    const rows = screen.getAllByRole('listitem')
    expect(rows).toHaveLength(3)
    expect(within(rows[1]).getByText('Renewal 1')).toBeInTheDocument()
    expect(within(rows[1]).getByText('3')).toBeInTheDocument()
    expect(within(rows[2]).getByText('Renewal 2')).toBeInTheDocument()
    expect(within(rows[2]).getByText(`renewal ${formatDate('2027-11-30')}, expiry ${formatDate('2027-12-31')}`)).toBeInTheDocument()
    expect(within(rows[2]).getAllByText('Ada Alberti')).toHaveLength(1)
  })

  it('offers "Undo renewal" only on the latest renewal', () => {
    renderSection(contractWithPermissions({ renew: true }, { renewals: TWO_RENEWALS, renewal_number: 2 }))

    const rows = screen.getAllByRole('listitem')
    expect(within(rows[1]).queryByRole('button', { name: 'Undo renewal' })).not.toBeInTheDocument()
    expect(within(rows[2]).getByRole('button', { name: 'Undo renewal' })).toBeInTheDocument()
  })

  it('hides the undo button without the renew permission', () => {
    renderSection(contractWithPermissions({ renew: false }, { renewals: TWO_RENEWALS, renewal_number: 2 }))
    expect(screen.queryByRole('button', { name: 'Undo renewal' })).not.toBeInTheDocument()
  })

  it('asks for confirmation, then sends DELETE and hands the contract back', async () => {
    const updated = contractWithPermissions({ renew: true }, { renewals: [TWO_RENEWALS[0]], renewal_number: 1 })
    vi.mocked(undoContractRenewal).mockResolvedValue(updated)
    const { onChanged, confirm } = renderSection(
      contractWithPermissions({ renew: true }, { renewals: TWO_RENEWALS, renewal_number: 2 }),
    )

    fireEvent.click(screen.getByRole('button', { name: 'Undo renewal' }))

    await waitFor(() => expect(undoContractRenewal).toHaveBeenCalledWith(1, 2))
    expect(confirm).toHaveBeenCalled()
    await waitFor(() => expect(onChanged).toHaveBeenCalled())
  })

  it('does not call the API when the confirmation is declined', async () => {
    const decline = vi.fn<ConfirmFn>().mockResolvedValue(false)
    renderSection(contractWithPermissions({ renew: true }, { renewals: TWO_RENEWALS, renewal_number: 2 }), decline)

    fireEvent.click(screen.getByRole('button', { name: 'Undo renewal' }))

    await waitFor(() => expect(decline).toHaveBeenCalled())
    expect(undoContractRenewal).not.toHaveBeenCalled()
  })
})
