import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { TreasuryMovementDialog } from '@/features/treasury-movements/treasury-movement-dialog'
import type { SubAccountKind, SubAccountOption } from '@/features/treasury-movements/types'

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const SUB_ACCOUNTS: Record<SubAccountKind, SubAccountOption[]> = {
  cost: [{ kind: 'cost', id: 1, label: '60.01 Cancelleria' }],
  revenue: [{ kind: 'revenue', id: 2, label: '70.01 Servizi' }],
  financial: [{ kind: 'financial', id: 3, label: '90.01 Giroconti' }],
}

const createMock = vi.fn()
const fetchOptionsMock = vi.fn((kind: SubAccountKind) => Promise.resolve(SUB_ACCOUNTS[kind]))
vi.mock('@/features/treasury-movements/api', () => ({
  createTreasuryMovement: (payload: unknown) => createMock(payload),
  updateTreasuryMovement: vi.fn(),
  fetchTreasuryMovement: vi.fn(),
  fetchSubAccountOptions: (kind: SubAccountKind) => fetchOptionsMock(kind),
}))

const fetchAccountMock = vi.fn()
vi.mock('@/features/financial-accounts/api', () => ({
  fetchFinancialAccount: (id: number) => fetchAccountMock(id),
}))

const pickerCalls = vi.hoisted(() => [] as { label: string; params?: Record<string, number> }[])
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    labels,
    params,
    disabled,
    ...rest
  }: {
    value: number | null
    onChange: (value: number | null) => void
    labels: { triggerLabel: string }
    params?: Record<string, number>
    disabled?: boolean
    id?: string
    'aria-describedby'?: string
    'aria-invalid'?: boolean
  }) => {
    pickerCalls.push({ label: labels.triggerLabel, params })
    return (
      <select
        id={rest.id}
        aria-describedby={rest['aria-describedby']}
        aria-invalid={rest['aria-invalid']}
        aria-label={labels.triggerLabel}
        disabled={disabled}
        value={value === null ? '' : String(value)}
        onChange={(event) => onChange(event.target.value === '' ? null : Number(event.target.value))}
      >
        <option value="">-</option>
        <option value="3">Conto 3</option>
        <option value="7">Conto 7</option>
      </select>
    )
  },
}))

const label = (key: string) => i18n.t(key)
const pattern = (key: string) => new RegExp(`^${label(key)}\\s*\\*?$`)

function renderDialog() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
  render(<TreasuryMovementDialog target={{ id: null }} onClose={vi.fn()} onSaved={vi.fn()} />, { wrapper })
}

async function pickType(type: string) {
  fireEvent.keyDown(screen.getByRole('combobox', { name: pattern('treasuryMovements.form.type') }), { key: 'ArrowDown' })
  fireEvent.click(await screen.findByRole('option', { name: label(`treasuryMovements.type.${type}`) }))
}

async function optionsOfSubAccount(): Promise<string[]> {
  fireEvent.keyDown(screen.getByRole('combobox', { name: pattern('treasuryMovements.form.subAccount') }), {
    key: 'ArrowDown',
  })
  await screen.findAllByRole('option')
  return screen.getAllByRole('option').map((option) => option.textContent ?? '')
}

const field = (key: string) => screen.getByLabelText(pattern(key))
const save = () => fireEvent.click(screen.getByRole('button', { name: label('treasuryMovements.form.save') }))

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  createMock.mockReset()
  fetchAccountMock.mockReset()
  fetchOptionsMock.mockClear()
  pickerCalls.length = 0
})

describe('TreasuryMovementDialog (spec 0355 AC-020)', () => {
  it('shows only the fields of the type: destination for a transfer, direction for an adjustment', async () => {
    renderDialog()
    expect(screen.queryByRole('combobox', { name: pattern('treasuryMovements.form.counterpart') })).not.toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: pattern('treasuryMovements.form.direction') })).not.toBeInTheDocument()

    await pickType('transfer')
    expect(screen.getByRole('combobox', { name: pattern('treasuryMovements.form.counterpart') })).toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: pattern('treasuryMovements.form.direction') })).not.toBeInTheDocument()

    await pickType('adjustment')
    expect(screen.getByRole('combobox', { name: pattern('treasuryMovements.form.direction') })).toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: pattern('treasuryMovements.form.counterpart') })).not.toBeInTheDocument()
  })

  it('offers only the sub-accounts the type allows', async () => {
    renderDialog()
    expect(await optionsOfSubAccount()).toEqual(['70.01 Servizi', '90.01 Giroconti'])
    expect(fetchOptionsMock).not.toHaveBeenCalledWith('cost')
    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'Escape' })

    await pickType('expense')
    expect(await optionsOfSubAccount()).toEqual(['60.01 Cancelleria', '90.01 Giroconti'])
    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'Escape' })

    await pickType('transfer')
    expect(await optionsOfSubAccount()).toEqual(['90.01 Giroconti'])
  })

  it('restricts the destination to the company of the origin account and excludes the origin', async () => {
    fetchAccountMock.mockResolvedValue({ id: 3, company: { id: 5, name: 'Acme' } })
    renderDialog()
    await pickType('transfer')
    expect(screen.getByRole('combobox', { name: pattern('treasuryMovements.form.counterpart') })).toBeDisabled()

    fireEvent.change(screen.getByRole('combobox', { name: pattern('treasuryMovements.form.account') }), {
      target: { value: '3' },
    })

    await waitFor(() =>
      expect(pickerCalls.some((call) => call.label === label('treasuryMovements.form.counterpart') && call.params?.company_id === 5)).toBe(true),
    )
    expect(fetchAccountMock).toHaveBeenCalledWith(3)
  })

  it('requires amount, description, account and sub-account with the accessible triad', async () => {
    renderDialog()
    save()

    const amountAlert = await screen.findByText(label('treasuryMovements.form.errors.amountInvalid'))
    expect(amountAlert).toHaveAttribute('role', 'alert')
    expect(field('treasuryMovements.form.amount')).toHaveAttribute('aria-invalid', 'true')
    expect(field('treasuryMovements.form.amount').getAttribute('aria-describedby')).toContain(amountAlert.id)
    expect(screen.getByText(label('treasuryMovements.form.errors.descriptionRequired'))).toHaveAttribute('role', 'alert')
    expect(screen.getByText(label('treasuryMovements.form.errors.subAccountRequired'))).toHaveAttribute('role', 'alert')
    expect(screen.getByText(label('treasuryMovements.form.errors.accountRequired'))).toHaveAttribute('role', 'alert')
    expect(createMock).not.toHaveBeenCalled()
  })

  it('rejects a zero amount', async () => {
    renderDialog()
    fireEvent.change(field('treasuryMovements.form.amount'), { target: { value: '0' } })
    save()

    expect(await screen.findByText(label('treasuryMovements.form.errors.amountInvalid'))).toBeInTheDocument()
  })

  it('sends exactly one sub-account id', async () => {
    createMock.mockResolvedValue({ id: 1 })
    renderDialog()
    fireEvent.change(screen.getByRole('combobox', { name: pattern('treasuryMovements.form.account') }), {
      target: { value: '7' },
    })
    fireEvent.change(field('treasuryMovements.form.amount'), { target: { value: '12,50' } })
    fireEvent.change(field('treasuryMovements.form.description'), { target: { value: ' Canone ' } })
    await optionsOfSubAccount()
    fireEvent.click(screen.getByRole('option', { name: '70.01 Servizi' }))
    save()

    await waitFor(() => expect(createMock).toHaveBeenCalledTimes(1))
    const payload = createMock.mock.calls[0][0] as Record<string, unknown>
    expect(payload).toMatchObject({
      type: 'income',
      direction: null,
      financial_account_id: 7,
      counterpart_account_id: null,
      amount: '12.50',
      description: 'Canone',
      revenue_type_id: 2,
      registry_id: null,
      work_order_id: null,
    })
    // REQUIREMENT CHANGED (spec 0355): the other two ids travel as null, so an update that changes the
    // sub-account kind clears the old one (the backend merges the body over the saved movement).
    expect(payload).toMatchObject({ cost_type_id: null, treasury_type_id: null })
  })
})
