import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { UserDetailView } from '@/features/users/user-detail'
import {
  READ_ONLY,
  confirmOpenRow,
  focusedInput,
  pencilOf,
  permissionsFor,
  userFixture,
  userWrapper,
} from '@/features/users/user-test-fixtures'
import type { UserDetailWithPermissions } from '@/features/users/types'

/**
 * Spec 0222: the user detail edits in place, one row at a time — a save is a
 * PATCH of that row alone (AC-002), the password row carries its confirmation,
 * and the cascading switches open only when the field they empty is editable
 * (AC-003, D-4).
 */

const fetchUserMock = vi.fn()
const createUserMock = vi.fn()
const updateUserMock = vi.fn()

vi.mock('@/features/users/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/users/api')>()),
  fetchUser: (...args: unknown[]) => fetchUserMock(...args),
  createUser: (...args: unknown[]) => createUserMock(...args),
  updateUser: (...args: unknown[]) => updateUserMock(...args),
  uploadUserAvatar: vi.fn(),
  deleteUserAvatar: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

// The quick-create "+" (spec 0028) is gated by `Can`/`useAbilities`, which needs
// an `AuthProvider` these suites do not render: deny everything so it never mounts.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/features/config/use-config', async () => {
  const { USER_ENUMS } = await import('@/features/users/user-test-fixtures')
  return {
    useConfig: () => ({ data: { enums: USER_ENUMS } }),
    useEnumOptions: (key: string) => USER_ENUMS[key] ?? [],
  }
})

vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: vi.fn().mockResolvedValue({ items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }),
}))

vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/users/user-test-stubs')).StubAsyncSelect,
}))
vi.mock('@/components/ui/async-paginated-multi-select', async () => ({
  AsyncPaginatedMultiSelect: (await import('@/features/users/user-test-stubs')).StubMultiSelect,
}))
vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => <div>activity-log-section</div>,
}))

async function renderDetail(user: UserDetailWithPermissions) {
  fetchUserMock.mockResolvedValue(user)
  const { client, wrapper } = userWrapper()
  render(<UserDetailView userId={user.id} />, { wrapper })
  await screen.findByRole('button', { name: 'Edit Personal details' })
  return client
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.restoreAllMocks()
  fetchUserMock.mockReset()
  createUserMock.mockReset()
  updateUserMock.mockReset()
  createUserMock.mockResolvedValue(userFixture())
  updateUserMock.mockResolvedValue(userFixture())
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
})

describe('UserDetailView — in-place editing (spec 0222)', () => {
  it('PATCHes the edited row alone and seeds the cache with the permissions', async () => {
    const saved = userFixture({ is_active: false })
    updateUserMock.mockResolvedValue(saved)
    const client = await renderDetail(userFixture())

    fireEvent.click(pencilOf('Active'))
    fireEvent.click(screen.getByRole('switch', { name: 'Active' }))
    confirmOpenRow('Save')

    await waitFor(() => expect(updateUserMock).toHaveBeenCalledWith(7, { is_active: false }))
    expect(client.getQueryData<UserDetailWithPermissions>(['users', 'detail', 7])?.permissions).toEqual(saved.permissions)
  })

  it('sends a new password with its confirmation, and says the first access is pending', async () => {
    await renderDetail(userFixture({ must_set_password: true }))

    expect(screen.getByText('First sign-in pending')).toBeInTheDocument()
    fireEvent.click(pencilOf('Password'))
    fireEvent.change(focusedInput('Password'), { target: { value: 'secret123' } })
    fireEvent.change(screen.getByLabelText(/^Confirm password/), { target: { value: 'secret123' } })
    confirmOpenRow('Save')

    await waitFor(() =>
      expect(updateUserMock).toHaveBeenCalledWith(7, { password: 'secret123', password_confirmation: 'secret123' }),
    )
  })

  it('keeps the password row open on a mismatched confirmation, without calling the server', async () => {
    await renderDetail(userFixture())

    fireEvent.click(pencilOf('Password'))
    fireEvent.change(focusedInput('Password'), { target: { value: 'secret123' } })
    fireEvent.change(screen.getByLabelText(/^Confirm password/), { target: { value: 'other1234' } })
    confirmOpenRow('Save')

    expect(await screen.findByText('Passwords do not match.')).toBeInTheDocument()
    expect(updateUserMock).not.toHaveBeenCalled()
  })

  it('closes on Cancel without calling the server', async () => {
    await renderDetail(userFixture())

    fireEvent.click(pencilOf('Email'))
    fireEvent.change(focusedInput('Email'), { target: { value: 'changed@example.com' } })
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(screen.getAllByText('ada@example.com').length).toBeGreaterThan(0)
    expect(updateUserMock).not.toHaveBeenCalled()
  })

  it('offers no pencil on a field the actor cannot edit, nor on the jolly switch whose rows are read-only', async () => {
    await renderDetail(
      userFixture({ permissions: permissionsFor({ email: READ_ONLY, 'employment.product_lines': READ_ONLY }) }),
    )

    expect(screen.queryByRole('button', { name: 'Edit Email' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Competent for all categories' })).not.toBeInTheDocument()
    expect(pencilOf('Roles')).toBeInTheDocument()
  })

  it('offers no Edit action: the record edits in place (spec 0222 AC-001)', async () => {
    await renderDetail(userFixture())

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
  })
})
