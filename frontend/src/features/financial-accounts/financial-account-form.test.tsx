import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { FinancialAccountForm } from '@/features/financial-accounts/financial-account-form'
import type {
  FinancialAccountDetailWithPermissions,
  FinancialAccountFormMode,
} from '@/features/financial-accounts/types'
import type { ResourcePermissions } from '@/features/authorization/types'

const createMock = vi.fn()
const updateMock = vi.fn()

vi.mock('@/features/financial-accounts/api', () => ({
  createFinancialAccount: (...args: unknown[]) => createMock(...args),
  updateFinancialAccount: (...args: unknown[]) => updateMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const forSelectMock = vi.fn()
vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: (...args: unknown[]) => forSelectMock(...args),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/geo/geo-select', () => ({
  GeoSelect: () => <div>geo-select</div>,
}))

const PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: false },
  fields: {},
  actions: {},
}

vi.mock('@/features/financial-accounts/use-financial-account-form-meta', () => ({
  useFinancialAccountFormMeta: () => ({ status: 'ready', permissions: PERMISSIONS }),
}))

const VALID_IBAN = 'IT60X0542811101000000123456'

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function renderForm(mode: FinancialAccountFormMode, onSuccess = vi.fn()) {
  return render(<FinancialAccountForm mode={mode} onSuccess={onSuccess} onCancel={vi.fn()} />, {
    wrapper: wrapper(),
  })
}

function cardAccount(
  overrides: Partial<FinancialAccountDetailWithPermissions> = {},
): FinancialAccountDetailWithPermissions {
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
    linked_account: { id: 3, name: 'Intesa CC', iban: VALID_IBAN },
    card_holder: 'Mario Rossi',
    card_number_masked: '**** 1111',
    card_expiry: '12/2030',
    notes: null,
    created_at: '2026-01-01T09:00:00Z',
    updated_at: '2026-01-01T09:00:00Z',
    permissions: PERMISSIONS,
    ...overrides,
  }
}

async function chooseOption(combobox: RegExp, option: RegExp | string) {
  fireEvent.keyDown(screen.getByRole('combobox', { name: combobox }), { key: 'Enter' })
  fireEvent.click(await screen.findByRole('option', { name: option }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createMock.mockReset()
  updateMock.mockReset()
  forSelectMock.mockReset()
  forSelectMock.mockResolvedValue({
    items: [],
    export_link: null,
    pagination: { total: 0, offset: 0, limit: 25, total_pages: 0 },
  })
})

describe('FinancialAccountForm create: fields per type (AC-022)', () => {
  it('shows the bank account fields by default and no card or CVV field', () => {
    renderForm({ type: 'create' })

    expect(screen.getByLabelText(/^Bank/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^IBAN/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Account number/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Address/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Notes/)).toBeInTheDocument()
    expect(screen.queryByLabelText(/^Card number/)).not.toBeInTheDocument()
    expect(screen.queryByLabelText(/cvv|ccv|pin|password/i)).not.toBeInTheDocument()
  })

  it('shows only the card fields after picking Card', async () => {
    renderForm({ type: 'create' })
    await chooseOption(/^Type/, /^Card$/)

    expect(await screen.findByLabelText(/^Card holder/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Card number/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Expiry/)).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: /^Card type/ })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: /^Circuit/ })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: /^Linked account/ })).toBeInTheDocument()
    expect(screen.queryByLabelText(/^IBAN/)).not.toBeInTheDocument()
    expect(screen.queryByLabelText(/cvv|ccv|pin|password/i)).not.toBeInTheDocument()
  })

  it('shows only the cash fields after picking Cash', async () => {
    renderForm({ type: 'create' })
    await chooseOption(/^Type/, /^Cash$/)

    await waitFor(() => expect(screen.queryByLabelText(/^IBAN/)).not.toBeInTheDocument())
    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.queryByLabelText(/^Card number/)).not.toBeInTheDocument()
  })

  it('feeds the linked-account picker with bank accounts only', async () => {
    renderForm({ type: 'create' })
    await chooseOption(/^Type/, /^Card$/)

    fireEvent.click(await screen.findByRole('combobox', { name: /^Linked account/ }))

    await waitFor(() =>
      expect(forSelectMock).toHaveBeenCalledWith(
        'financial-accounts',
        expect.objectContaining({ params: { type: 'bank_account' } }),
      ),
    )
  })
})

describe('FinancialAccountForm create: validation and submit', () => {
  it('shows an accessible error on an invalid IBAN and does not call the API', async () => {
    renderForm({ type: 'create' })

    fireEvent.change(screen.getByLabelText(/^Bank/), { target: { value: 'Intesa' } })
    fireEvent.change(screen.getByLabelText(/^IBAN/), {
      target: { value: 'IT61X0542811101000000123456' },
    })
    fireEvent.change(screen.getByLabelText(/^Account number/), { target: { value: '123' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    const ibanInput = screen.getByLabelText(/^IBAN/)
    await waitFor(() => expect(ibanInput).toHaveAttribute('aria-invalid', 'true'))
    const message = screen.getByText('The IBAN is not valid.')
    expect(message).toHaveAttribute('role', 'alert')
    expect(ibanInput.getAttribute('aria-describedby')).toContain(message.id)
    expect(createMock).not.toHaveBeenCalled()
  })

  it('submits only the bank account fields with a normalized IBAN', async () => {
    createMock.mockResolvedValue(cardAccount({ type: 'bank_account' }))
    renderForm({ type: 'create' })

    fireEvent.change(screen.getByLabelText(/^Bank/), { target: { value: 'Intesa' } })
    fireEvent.change(screen.getByLabelText(/^IBAN/), {
      target: { value: 'it60 x054 2811 1010 0000 0123 456' },
    })
    fireEvent.change(screen.getByLabelText(/^Account number/), { target: { value: '123' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createMock).toHaveBeenCalledTimes(1))
    const payload = createMock.mock.calls[0][0]
    expect(payload).toMatchObject({
      type: 'bank_account',
      name: 'Intesa',
      iban: VALID_IBAN,
      account_number: '123',
      company_id: null,
    })
    expect(payload).not.toHaveProperty('card_number')
    expect(payload).not.toHaveProperty('card_type')
  })

  it('requires the linked account for a credit card', async () => {
    renderForm({ type: 'create' })
    await chooseOption(/^Type/, /^Card$/)

    await screen.findByLabelText(/^Card holder/)
    await chooseOption(/^Card type/, 'Credit')
    await chooseOption(/^Circuit/, 'Visa')
    fireEvent.change(screen.getByLabelText(/^Bank/), { target: { value: 'Intesa' } })
    fireEvent.change(screen.getByLabelText(/^Card holder/), { target: { value: 'Mario Rossi' } })
    fireEvent.change(screen.getByLabelText(/^Card number/), {
      target: { value: '4111 1111 1111 1111' },
    })
    fireEvent.change(screen.getByLabelText(/^Expiry/), { target: { value: '12/2030' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(
      await screen.findByText('A credit card must be linked to a bank account.'),
    ).toBeInTheDocument()
    expect(createMock).not.toHaveBeenCalled()
  })
})

describe('FinancialAccountForm edit (AC-023)', () => {
  it('locks the type and shows the masked number as placeholder, never a value', () => {
    renderForm({ type: 'edit', financialAccount: cardAccount() })

    expect(screen.getByRole('combobox', { name: /^Type/ })).toBeDisabled()
    const number = screen.getByLabelText(/^Card number/)
    expect(number).toHaveValue('')
    expect(number).toHaveAttribute('placeholder', '**** 1111')
  })

  it('omits card_number when the user does not type a new one', async () => {
    updateMock.mockResolvedValue(cardAccount())
    renderForm({ type: 'edit', financialAccount: cardAccount() })

    fireEvent.change(screen.getByLabelText(/^Card holder/), { target: { value: 'Mario Bianchi' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateMock.mock.calls[0]
    expect(id).toBe(7)
    expect(payload).toMatchObject({
      type: 'card',
      card_holder: 'Mario Bianchi',
      linked_account_id: 3,
    })
    expect(payload).not.toHaveProperty('card_number')
  })

  it('replaces the number only when a new one is typed', async () => {
    updateMock.mockResolvedValue(cardAccount())
    renderForm({ type: 'edit', financialAccount: cardAccount() })

    fireEvent.change(screen.getByLabelText(/^Card number/), {
      target: { value: '378282246310005' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateMock).toHaveBeenCalledTimes(1))
    expect(updateMock.mock.calls[0][1]).toMatchObject({ card_number: '378282246310005' })
  })
})
