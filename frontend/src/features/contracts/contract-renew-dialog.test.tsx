import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { contracts as contractsEn } from '@/i18n/locales/en-contracts'
import { ContractRenewDialog } from '@/features/contracts/contract-renew-dialog'
import { renewContract } from '@/features/contracts/api'
import { contractWithPermissions, contractFixture } from '@/features/contracts/contract-renewal-fixtures'

/** Spec 0358 AC-027: prefilled dates, accessible validation, frozen payload shape. */

vi.mock('@/features/contracts/api', () => ({
  renewContract: vi.fn(),
  contractDetailQueryKey: (id: number | null) => ['contracts', 'detail', id],
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function renderDialog() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const onOpenChange = vi.fn()
  const onRenewed = vi.fn()
  render(
    <QueryClientProvider client={client}>
      <ContractRenewDialog open onOpenChange={onOpenChange} contract={contractFixture()} onRenewed={onRenewed} />
    </QueryClientProvider>,
  )
  return { onOpenChange, onRenewed }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { contracts: contractsEn }, true, true)
})

beforeEach(() => {
  vi.mocked(renewContract).mockReset()
})

describe('ContractRenewDialog', () => {
  it('prefills the dates from the current period duration', () => {
    renderDialog()
    expect(screen.getByLabelText(/^New expiry date/)).toHaveValue('2027-12-31')
    expect(screen.getByLabelText(/^New renewal date/)).toHaveValue('2027-11-30')
  })

  it('blocks an empty expiry date with a wired accessible error', async () => {
    renderDialog()
    fireEvent.change(screen.getByLabelText(/^New expiry date/), { target: { value: '' } })
    fireEvent.click(screen.getByRole('button', { name: 'Renew' }))

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('The new expiry date is required.')
    const field = screen.getByLabelText(/^New expiry date/)
    expect(field).toHaveAttribute('aria-invalid', 'true')
    expect(field.getAttribute('aria-describedby')).toContain(alert.id)
    expect(renewContract).not.toHaveBeenCalled()
  })

  it('blocks a renewal date later than the expiry date', async () => {
    renderDialog()
    fireEvent.change(screen.getByLabelText(/^New renewal date/), { target: { value: '2028-01-15' } })
    fireEvent.click(screen.getByRole('button', { name: 'Renew' }))

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('The renewal date cannot be later than the expiry date.')
    expect(screen.getByLabelText(/^New renewal date/)).toHaveAttribute('aria-invalid', 'true')
    expect(renewContract).not.toHaveBeenCalled()
  })

  it('sends the frozen payload, then hands the refreshed contract back and closes', async () => {
    const updated = contractWithPermissions({ renew: true }, { renewal_number: 1 })
    vi.mocked(renewContract).mockResolvedValue(updated)
    const { onOpenChange, onRenewed } = renderDialog()

    fireEvent.click(screen.getByRole('button', { name: 'Renew' }))

    await waitFor(() => expect(onRenewed).toHaveBeenCalledWith(updated))
    expect(renewContract).toHaveBeenCalledWith(1, { renewal_date: '2027-11-30', expiry_date: '2027-12-31' })
    expect(onOpenChange).toHaveBeenCalledWith(false)
  })

  it('sends a null renewal date when the field is cleared', async () => {
    vi.mocked(renewContract).mockResolvedValue(contractWithPermissions({ renew: true }))
    renderDialog()

    fireEvent.change(screen.getByLabelText(/^New renewal date/), { target: { value: '' } })
    fireEvent.click(screen.getByRole('button', { name: 'Renew' }))

    await waitFor(() => expect(renewContract).toHaveBeenCalled())
    expect(renewContract).toHaveBeenCalledWith(1, { renewal_date: null, expiry_date: '2027-12-31' })
  })
})
