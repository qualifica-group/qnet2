import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { AxiosError, type AxiosResponse } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { UserDetailView } from '@/features/users/user-detail'
import {
  HIDDEN,
  confirmOpenRow,
  employmentFixture,
  pencilOf,
  permissionsFor,
  userFixture,
  userWrapper,
} from '@/features/users/user-test-fixtures'
import type { UserDetailWithPermissions } from '@/features/users/types'

/**
 * Spec 0272 AC-015: the in-force condition is read only, the "Pagamento"
 * section appears only with `users.manageCompensation`, the IBAN reads in
 * groups of four and a 422 lands under it, the termination date explains the
 * deactivation. Spec 0277 AC-005: the hourly cost derived from the salary
 * reads there, read-only.
 */

const fetchUserMock = vi.fn()
const updateUserMock = vi.fn()
let granted: string[] = []

vi.mock('@/features/users/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/users/api')>()),
  fetchUser: (...args: unknown[]) => fetchUserMock(...args),
  updateUser: (...args: unknown[]) => updateUserMock(...args),
  uploadUserAvatar: vi.fn(),
  deleteUserAvatar: vi.fn(),
}))
vi.mock('@/features/users/contract-history-api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/users/contract-history-api')>()),
  fetchEmploymentContracts: () => Promise.resolve([]),
}))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => granted.includes(permission), hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => Promise.resolve({ fields: [], permissions: permissionsFor() }),
}))
vi.mock('@/features/config/use-config', async () => {
  const { USER_ENUMS } = await import('@/features/users/user-test-fixtures')
  return {
    useConfig: () => ({ data: { enums: USER_ENUMS } }),
    useEnumOptions: (key: string) => USER_ENUMS[key] ?? [],
  }
})
vi.mock('@/features/activity-log/activity-log-section', () => ({ ActivityLogSection: () => <div /> }))

function withPayment(overrides: Partial<UserDetailWithPermissions> = {}) {
  return userFixture({
    employment: employmentFixture({
      relationship_type: 'self_employed',
      company: { id: 5, label: 'Acme Srl' },
      company_id: 5,
      current_contract: { starts_on: '2026-03-01', contract_type: { id: 2, name: 'Apprenticeship' }, working_time: 'part_time', contract_expires_on: '2026-12-31' },
      iban: 'IT60X0542811101000000123456',
      iban_holder: 'Ada Lovelace',
      hourly_cost: 14.45,
    }),
    ...overrides,
  })
}

async function renderDetail(user: UserDetailWithPermissions) {
  fetchUserMock.mockResolvedValue(user)
  const { wrapper } = userWrapper()
  render(<UserDetailView userId={user.id} />, { wrapper })
  await screen.findByRole('button', { name: 'Edit Personal details' })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  granted = []
  updateUserMock.mockResolvedValue(userFixture())
})

describe('UserDetailView — contract condition in force (spec 0272 D-2)', () => {
  // REQUIREMENT CHANGED (spec 0293): the CCNL level row is gone.
  it('shows relationship type, company, contract data and start as read-only rows', async () => {
    await renderDetail(withPayment())

    expect(screen.getByText('Contract type')).toBeInTheDocument()
    expect(screen.getByText('Apprenticeship')).toBeInTheDocument()
    expect(screen.queryByText('Collective agreement and level')).not.toBeInTheDocument()
    expect(screen.getByText('01/03/2026')).toBeInTheDocument()
    expect(screen.getByText('Working time')).toBeInTheDocument()
    expect(screen.getByText('Part time')).toBeInTheDocument()
    expect(screen.getByText('Contract expiry')).toBeInTheDocument()
    expect(screen.getByText('31/12/2026')).toBeInTheDocument()
    expect(screen.getByText('Acme Srl')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Relationship type' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Company' })).not.toBeInTheDocument()
  })

  it('shows the contract type empty when the condition has none, and hides it without employment.contracts (AC-013)', async () => {
    await renderDetail(
      withPayment({
        employment: employmentFixture({
          relationship_type: 'self_employed',
          current_contract: { starts_on: '2026-03-01', contract_type: null, working_time: null, contract_expires_on: null },
        }),
      }),
    )
    expect(screen.getByText('Contract type')).toBeInTheDocument()
    expect(screen.queryByText('Apprenticeship')).not.toBeInTheDocument()
    expect(screen.getByText('Working time')).toBeInTheDocument()
    expect(screen.queryByText('Part time')).not.toBeInTheDocument()
  })

  it('hides the contract type row when employment.contracts is hidden', async () => {
    await renderDetail(withPayment({ permissions: permissionsFor({ 'employment.contracts': HIDDEN }) }))

    expect(screen.queryByText('Contract type')).not.toBeInTheDocument()
    expect(screen.queryByText('Apprenticeship')).not.toBeInTheDocument()
    expect(screen.queryByText('Working time')).not.toBeInTheDocument()
    expect(screen.queryByText('Contract expiry')).not.toBeInTheDocument()
    expect(screen.queryByText('Part time')).not.toBeInTheDocument()
  })

  it('explains under the termination date that the person is deactivated', async () => {
    await renderDetail(withPayment())

    expect(screen.getByText(/the person is deactivated/)).toBeInTheDocument()
  })
})

describe('UserDetailView — Pagamento (spec 0272 D-5/D-6)', () => {
  it('is not rendered without users.manageCompensation', async () => {
    await renderDetail(withPayment())

    expect(screen.queryByText('Payment')).not.toBeInTheDocument()
    expect(screen.queryByText('IBAN')).not.toBeInTheDocument()
    expect(screen.queryByText('Hourly cost')).not.toBeInTheDocument()
  })

  it('is not rendered when the field metadata hides the IBAN, even with the permission', async () => {
    granted = ['users.manageCompensation']
    await renderDetail(withPayment({ permissions: permissionsFor({ 'employment.iban': HIDDEN }) }))

    expect(screen.queryByText('IBAN')).not.toBeInTheDocument()
  })

  it('shows the IBAN in groups of four with its holder', async () => {
    granted = ['users.manageCompensation']
    await renderDetail(withPayment())

    expect(screen.getByText('Payment')).toBeInTheDocument()
    expect(screen.getByText('IT60 X054 2811 1010 0000 0123 456')).toBeInTheDocument()
    expect(screen.getByText('Account holder')).toBeInTheDocument()
  })

  it('shows the hourly cost derived from the salary, read-only', async () => {
    granted = ['users.manageCompensation']
    await renderDetail(withPayment())

    expect(screen.getByText('Hourly cost')).toBeInTheDocument()
    expect(screen.getByText(/14,45.*\/h/)).toBeInTheDocument()
    expect(screen.getByText('Derived from the salary of the contract condition in force today.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Hourly cost' })).not.toBeInTheDocument()
  })

  it('saves an edited IBAN inline and shows a 422 under it', async () => {
    granted = ['users.manageCompensation']
    updateUserMock.mockRejectedValue(
      new AxiosError('bad', 'ERR', undefined, undefined, {
        status: 422,
        data: { errors: { 'employment.iban': ['The IBAN is not valid.'] } },
      } as AxiosResponse),
    )
    await renderDetail(withPayment())

    fireEvent.click(pencilOf('IBAN'))
    fireEvent.change(screen.getByLabelText('IBAN'), { target: { value: 'IT00WRONG' } })
    confirmOpenRow('Save')

    expect(await screen.findByText('The IBAN is not valid.')).toBeInTheDocument()
    await waitFor(() => expect(updateUserMock).toHaveBeenCalledTimes(1))
    expect(updateUserMock.mock.calls[0][1].employment).toMatchObject({ iban: 'IT00WRONG' })
  })
})
