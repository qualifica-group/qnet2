import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import i18n from '@/i18n'
import { UserDetailView } from '@/features/users/user-detail'
import { userWrapper } from '@/features/users/user-test-fixtures'
import type { UserDetailWithPermissions } from '@/features/users/types'
import { buildBalance, buildMovement } from '@/features/stock/stock-fixtures'

/** Spec 0307 AC-019: the "Dotazioni" section of the user record. */

const fetchUserMock = vi.fn()
const fetchBalancesMock = vi.fn()
const recordMock = vi.fn()
const { grantedAbilities } = vi.hoisted(() => ({ grantedAbilities: new Set<string>() }))

vi.mock('@/features/users/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/users/api')>()),
  fetchUser: (...args: unknown[]) => fetchUserMock(...args),
}))
vi.mock('@/features/stock/api', () => ({
  fetchStockBalances: (...args: unknown[]) => fetchBalancesMock(...args),
  recordStockMovement: (...args: unknown[]) => recordMock(...args),
}))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => grantedAbilities.has(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))
vi.mock('@/features/users/contract-history-api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/users/contract-history-api')>()),
  fetchEmploymentContracts: () => Promise.resolve([]),
}))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function user(): UserDetailWithPermissions {
  return {
    id: 7,
    name: 'Jane Doe',
    email: 'jane@example.com',
    locale: 'en',
    is_active: true,
    roles: [],
    avatar_url: null,
    must_set_password: false,
    managed_business_functions: [],
    created_at: null,
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: { view_activity: false },
    },
  }
}

function renderDetail() {
  fetchUserMock.mockResolvedValue(user())
  const { client, wrapper } = userWrapper()
  const invalidate = vi.spyOn(client, 'invalidateQueries')
  render(<UserDetailView userId={7} />, { wrapper })
  return invalidate
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchUserMock.mockReset()
  fetchBalancesMock.mockReset()
  recordMock.mockReset()
  grantedAbilities.clear()
  fetchBalancesMock.mockResolvedValue([buildBalance({ quantity: '2.000', user: { id: 7, name: 'Jane Doe', is_active: true } })])
})

describe('UserDetailView - Equipment section (spec 0307 AC-019)', () => {
  it('lists the rows of the user with stock.viewAny and offers no Return without stock.manage', async () => {
    grantedAbilities.add('stock.viewAny')
    renderDetail()

    expect(await screen.findByText('Notebook (NB-01)')).toBeInTheDocument()
    expect(screen.getByText('2 pz')).toBeInTheDocument()
    expect(fetchBalancesMock).toHaveBeenCalledWith({ user_id: 7 })
    expect(screen.queryByRole('button', { name: 'Return' })).not.toBeInTheDocument()
  })

  it('shows the empty state for a user holding nothing', async () => {
    grantedAbilities.add('stock.viewAny')
    fetchBalancesMock.mockResolvedValue([])
    renderDetail()

    expect(await screen.findByText(i18n.t('stock.list.emptyUser'))).toBeInTheDocument()
  })

  it('has no section and sends no request without stock.viewAny', async () => {
    renderDetail()

    await waitFor(() => expect(screen.getAllByText('Jane Doe').length).toBeGreaterThan(0))
    expect(screen.queryByRole('heading', { name: i18n.t('stock.userSection.title') })).not.toBeInTheDocument()
    expect(fetchBalancesMock).not.toHaveBeenCalled()
  })

  it('returns the whole quantity after the confirmation with stock.manage and refreshes the stock', async () => {
    grantedAbilities.add('stock.viewAny')
    grantedAbilities.add('stock.manage')
    recordMock.mockResolvedValue({ movement: buildMovement({ type: 'RETURN' }), balances: [] })
    const invalidate = renderDetail()

    fireEvent.click(await screen.findByRole('button', { name: 'Return' }))
    fireEvent.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Return' }))

    await waitFor(() => expect(recordMock).toHaveBeenCalledTimes(1))
    expect(recordMock).toHaveBeenCalledWith({
      type: 'RETURN',
      product_id: 11,
      operational_site_id: 4,
      user_id: 7,
      quantity: '2.000',
    })
    expect(invalidate).toHaveBeenCalledWith({ queryKey: ['stock'] })
  })

  it('does not return when the confirmation is cancelled', async () => {
    grantedAbilities.add('stock.viewAny')
    grantedAbilities.add('stock.manage')
    renderDetail()

    fireEvent.click(await screen.findByRole('button', { name: 'Return' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Cancel' }))

    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(recordMock).not.toHaveBeenCalled()
  })
})
