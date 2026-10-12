import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { UserDetailView } from '@/features/users/user-detail'
import { UserForm } from '@/features/users/user-form'
import {
  HIDDEN,
  READ_ONLY,
  REQUIRED,
  focusedInput,
  pencilOf,
  permissionsFor,
  typeInRow,
  userFixture,
  userWrapper,
} from '@/features/users/user-test-fixtures'
import type { UserDetailWithPermissions } from '@/features/users/types'

/**
 * Spec 0004 AC11-16 on the record layout of spec 0222: a hidden field has no
 * row, a read-only one no pencil, a required one its marker in the editor; a
 * field missing from the metadata falls back to visible + editable; an action
 * gated off has no affordance; a 422 lands inline on the open row.
 * REQUIREMENT CHANGED (spec 0222): "disabled control" became "no pencil".
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

function renderCreate() {
  const { client, wrapper } = userWrapper()
  render(<UserForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper })
  return client
}

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

describe('UserForm — metadata-driven authorization (spec 0004)', () => {
  it('AC11/12/13 — no row for a hidden field, no pencil on a read-only one, the marker on a required one', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: permissionsFor({ email: REQUIRED, roles: HIDDEN, password: READ_ONLY }),
    })
    renderCreate()

    await screen.findByRole('button', { name: 'Edit Personal details' })
    expect(screen.queryByRole('button', { name: 'Edit Roles' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Password' })).not.toBeInTheDocument()

    fireEvent.click(pencilOf('Email'))
    const email = focusedInput('Email') as HTMLInputElement
    expect(email.labels?.[0]?.textContent).toContain('*')
  })

  it('AC16 — falls back to visible+editable when a field is missing from the metadata', async () => {
    fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor({ email: REQUIRED }) })
    renderCreate()

    await screen.findByRole('button', { name: 'Edit Personal details' })
    fireEvent.click(pencilOf('Password'))
    expect(focusedInput('Password')).toBeEnabled()
  })

  it('AC14 — hides an action affordance gated off by metadata', async () => {
    await renderDetail(
      userFixture({
        avatar_url: 'https://example.test/avatar.png',
        permissions: permissionsFor({}, { upload_avatar: false, delete_avatar: true }),
      }),
    )

    expect(screen.queryByRole('button', { name: 'Choose image' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Remove' })).toBeInTheDocument()
  })

  it('AC15 — seeds permissions from the loaded detail and surfaces a 422 field error inline', async () => {
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)
    updateUserMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { email: ['field not editable'] } },
      } as never),
    )
    await renderDetail(userFixture())

    typeInRow('Email', 'ada@other.example.com', 'Save')

    expect(await screen.findByText('field not editable')).toBeInTheDocument()
    expect(updateUserMock).toHaveBeenCalledTimes(1)
  })
})
