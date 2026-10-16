import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { AxiosError, type AxiosResponse } from 'axios'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import i18n from '@/i18n'
import type { EmploymentContract } from '@/features/users/contract-history-types'
import { userWrapper } from '@/features/users/user-test-fixtures'
import { UserContractHistoryCreateNote, UserContractHistorySection } from '@/features/users/user-contract-history-section'
import { toContractPayload } from '@/features/users/contract-history-schema'

/**
 * Spec 0272 D-1 / AC-015: the contract history section: list, "Nuova condizione"
 * (salary only with `users.manageCompensation`), delete of the last condition
 * only, and the 403/409/422 messages.
 */

const fetchMock = vi.fn()
const createMock = vi.fn()
const deleteMock = vi.fn()
const toastError = vi.fn()
let granted: string[] = []

vi.mock('@/features/users/contract-history-api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/users/contract-history-api')>()),
  fetchEmploymentContracts: (...args: unknown[]) => fetchMock(...args),
  createEmploymentContract: (...args: unknown[]) => createMock(...args),
  deleteEmploymentContract: (...args: unknown[]) => deleteMock(...args),
}))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => granted.includes(permission), hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: (...args: unknown[]) => toastError(...args) } }))
vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/users/user-test-stubs')).StubAsyncSelect,
}))

const LATEST: EmploymentContract = {
  id: 12,
  starts_on: '2026-03-01',
  ends_on: null,
  is_current: true,
  relationship_type: 'employee',
  company: { id: 5, label: 'Acme Srl' },
  collective_agreement_level: { id: 3, label: 'Commercio - Q1' },
  salary_amount: '2500.00',
  salary_period: 'monthly',
  change_reason: 'Promotion',
  created_at: null,
}
const OLDER: EmploymentContract = {
  ...LATEST,
  id: 11,
  starts_on: '2025-01-01',
  ends_on: '2026-02-28',
  is_current: false,
  collective_agreement_level: null,
  salary_amount: undefined,
  salary_period: undefined,
  change_reason: null,
}

function failure(status: number, errors?: Record<string, string[]>) {
  return new AxiosError('failed', 'ERR', undefined, undefined, { status, data: { errors } } as AxiosResponse)
}

function renderSection() {
  const { client, wrapper } = userWrapper()
  const invalidate = vi.spyOn(client, 'invalidateQueries')
  render(<UserContractHistorySection userId={7} />, { wrapper })
  return invalidate
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  granted = []
  fetchMock.mockResolvedValue([LATEST, OLDER])
})

describe('UserContractHistorySection — list', () => {
  it('lists the conditions with period, current badge, level and salary only when present', async () => {
    renderSection()

    expect(await screen.findByText(/From .* to ongoing/)).toBeInTheDocument()
    expect(screen.getByText('Current')).toBeInTheDocument()
    expect(screen.getByText(/Commercio - Q1/)).toBeInTheDocument()
    expect(screen.getByText(/2500.00 \/ month/)).toBeInTheDocument()
    expect(screen.getAllByText(/Acme Srl/)).toHaveLength(2)
    expect(screen.getByText('Reason: Promotion')).toBeInTheDocument()
    expect(screen.getAllByRole('listitem')).toHaveLength(2)
  })

  it('shows no write action without users.update', async () => {
    renderSection()
    await screen.findByText('Current')

    expect(screen.queryByRole('button', { name: 'New condition' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Delete last condition' })).not.toBeInTheDocument()
  })

  it('tells the history is filled in after saving, on create', () => {
    render(<UserContractHistoryCreateNote />)
    expect(screen.getByText('The contract history is filled in after the user is saved.')).toBeInTheDocument()
  })
})

describe('UserContractHistorySection — new condition', () => {
  async function openDialog() {
    const invalidate = renderSection()
    await screen.findByText('Current')
    fireEvent.click(screen.getByRole('button', { name: 'New condition' }))
    return { dialog: await screen.findByRole('dialog'), invalidate }
  }

  it('hides the salary fields without users.manageCompensation and never sends them', async () => {
    granted = ['users.update']
    createMock.mockResolvedValue(LATEST)
    const { dialog } = await openDialog()

    expect(within(dialog).queryByLabelText('Salary')).not.toBeInTheDocument()
    fireEvent.change(within(dialog).getByLabelText(/Start date/), { target: { value: '2026-06-01' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createMock).toHaveBeenCalledTimes(1))
    expect(createMock.mock.calls[0][1]).toEqual({
      starts_on: '2026-06-01',
      relationship_type: 'employee',
      company_id: null,
      collective_agreement_level_id: null,
      change_reason: null,
    })
  })

  it('sends salary with users.manageCompensation, picks the level, and refreshes the list AND the user detail', async () => {
    granted = ['users.update', 'users.manageCompensation']
    createMock.mockResolvedValue(LATEST)
    const { dialog, invalidate } = await openDialog()

    expect(within(dialog).getByTestId('resource-Collective agreement and level')).toHaveTextContent('collective-agreement-levels')
    fireEvent.change(within(dialog).getByLabelText(/Start date/), { target: { value: '2026-06-01' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'pick-Collective agreement and level' }))
    fireEvent.change(within(dialog).getByLabelText('Salary'), { target: { value: '3000' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }))

    expect(await within(dialog).findByText('Enter the salary period.')).toBeInTheDocument()
    expect(createMock).not.toHaveBeenCalled()

    fireEvent.click(within(dialog).getByRole('combobox', { name: 'Period' }))
    fireEvent.click(await screen.findByRole('option', { name: 'month' }))
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createMock).toHaveBeenCalledTimes(1))
    expect(createMock.mock.calls[0][1]).toMatchObject({
      collective_agreement_level_id: 1,
      salary_amount: 3000,
      salary_period: 'monthly',
    })
    await waitFor(() => {
      const keys = invalidate.mock.calls.map((call) => JSON.stringify((call[0] as { queryKey: unknown }).queryKey))
      expect(keys).toContain(JSON.stringify(['users', 'employment-contracts', 7]))
      expect(keys).toContain(JSON.stringify(['users', 'detail', 7]))
    })
  })

  it('shows a duplicated start date (422) under the field', async () => {
    granted = ['users.update']
    createMock.mockRejectedValue(failure(422, { starts_on: ['This start date is already used.'] }))
    const { dialog } = await openDialog()

    fireEvent.change(within(dialog).getByLabelText(/Start date/), { target: { value: '2026-03-01' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }))

    expect(await within(dialog).findByText('This start date is already used.')).toBeInTheDocument()
  })

  it('tells a 403 with the permission message', async () => {
    granted = ['users.update']
    createMock.mockRejectedValue(failure(403))
    const { dialog } = await openDialog()

    fireEvent.change(within(dialog).getByLabelText(/Start date/), { target: { value: '2026-06-01' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(toastError).toHaveBeenCalledWith('You do not have permission for this action.'))
  })
})

describe('UserContractHistorySection — delete the last condition', () => {
  it('offers delete on the latest condition only and removes it after confirmation', async () => {
    granted = ['users.update']
    deleteMock.mockResolvedValue(undefined)
    renderSection()
    await screen.findByText('Current')

    expect(screen.getAllByRole('button', { name: 'Delete last condition' })).toHaveLength(1)
    fireEvent.click(screen.getByRole('button', { name: 'Delete last condition' }))
    const buttons = await screen.findAllByRole('button', { name: 'Delete last condition' })
    fireEvent.click(buttons[buttons.length - 1])

    await waitFor(() => expect(deleteMock).toHaveBeenCalledWith(7, 12))
  })

  it('tells a 409 as "only the last condition"', async () => {
    granted = ['users.update']
    deleteMock.mockRejectedValue(failure(409))
    renderSection()
    await screen.findByText('Current')

    fireEvent.click(screen.getByRole('button', { name: 'Delete last condition' }))
    const buttons = await screen.findAllByRole('button', { name: 'Delete last condition' })
    fireEvent.click(buttons[buttons.length - 1])

    await waitFor(() => expect(toastError).toHaveBeenCalledWith('Only the last condition can be deleted.'))
  })
})

describe('toContractPayload', () => {
  const values = {
    starts_on: '2026-06-01',
    relationship_type: 'self_employed' as const,
    company_id: 2,
    collective_agreement_level_id: null,
    salary_amount: '12.5',
    salary_period: 'hourly' as const,
    change_reason: '  new deal ',
  }

  it('keeps the salary only for an actor who can manage it', () => {
    expect(toContractPayload(values, true)).toMatchObject({ salary_amount: 12.5, salary_period: 'hourly', change_reason: 'new deal' })
    expect(toContractPayload(values, false)).not.toHaveProperty('salary_amount')
  })
})
