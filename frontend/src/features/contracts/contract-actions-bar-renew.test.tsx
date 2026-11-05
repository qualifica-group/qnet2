import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ConfirmContext, type ConfirmFn } from '@/components/confirm-dialog-context'
import i18n from '@/i18n'
import { contracts as contractsEn } from '@/i18n/locales/en-contracts'
import { workOrders as workOrdersEn } from '@/i18n/locales/en-work-orders'
import { ContractActionsBar } from '@/features/contracts/contract-actions-bar'
import { contractWithPermissions } from '@/features/contracts/contract-renewal-fixtures'
import type { ContractDetailWithPermissions } from '@/features/contracts/types'

/** Spec 0358 AC-027: "Renew" needs BOTH the lifecycle rule (same as Program) and `permissions.actions.renew`. */

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/contracts/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/contracts/api')>()),
  fetchContractProgrammableLines: vi.fn().mockResolvedValue([]),
}))

function renderBar(contract: ContractDetailWithPermissions) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <ConfirmContext.Provider value={vi.fn<ConfirmFn>()}>
        <ContractActionsBar contract={contract} onChanged={vi.fn()} onWorkOrderCreated={vi.fn()} />
      </ConfirmContext.Provider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { contracts: contractsEn, workOrders: workOrdersEn }, true, true)
})

describe('ContractActionsBar - Renew', () => {
  it('shows "Renew" with the permission flag on a programmable contract and opens the dialog', async () => {
    renderBar(contractWithPermissions({ renew: true, program: true }))

    fireEvent.click(screen.getByRole('button', { name: 'Renew' }))

    expect(await screen.findByLabelText(/^New expiry date/)).toHaveValue('2027-12-31')
  })

  it('hides "Renew" without the permission flag', () => {
    renderBar(contractWithPermissions({ renew: false, program: true }))
    expect(screen.queryByRole('button', { name: 'Renew' })).not.toBeInTheDocument()
  })

  it('hides "Renew" when the lifecycle does not admit programming, flag or not', () => {
    renderBar(
      contractWithPermissions(
        { renew: true },
        { contract_status: { id: 2, name: 'Disdetto', color: null, group: 'closed_lost' } },
      ),
    )
    expect(screen.queryByRole('button', { name: 'Renew' })).not.toBeInTheDocument()
  })
})
