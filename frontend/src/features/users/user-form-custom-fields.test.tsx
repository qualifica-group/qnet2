import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'
import { UserDetailView } from '@/features/users/user-detail'
import { UserForm } from '@/features/users/user-form'
import {
  EDITABLE,
  clickCreateSave,
  confirmOpenRow,
  fillRequiredCreateRows,
  pencilOf,
  permissionsFor,
  userFixture,
  userWrapper,
} from '@/features/users/user-test-fixtures'
import type { UserDetailWithPermissions } from '@/features/users/types'

/**
 * Spec 0021 on the record layout of spec 0222: a custom field is a closed row
 * on create and an in-place row on the detail. REQUIREMENT CHANGED (spec
 * 0222): the control opens from its row.
 */

const PIPPO_FIELD: CustomFieldDescriptor = {
  key: 'custom.pippo',
  type: 'boolean',
  label: 'Pippo',
  group: null,
  mandatory: false,
  source: 'custom',
}

const PIPPO_PERMISSIONS = permissionsFor({ 'custom.pippo': EDITABLE })

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

describe('Users — custom fields (spec 0021)', () => {
  beforeEach(() => {
    fetchResourceMetaMock.mockResolvedValue({ fields: [PIPPO_FIELD], permissions: PIPPO_PERMISSIONS })
  })

  it('offers the custom field as a closed row on create', async () => {
    renderCreate()

    expect(await screen.findByRole('button', { name: 'Edit Pippo' })).toBeInTheDocument()
  })

  it('includes the valued custom field in the create payload', async () => {
    renderCreate()

    await fillRequiredCreateRows()
    fireEvent.click(await screen.findByRole('button', { name: 'Edit Pippo' }))
    fireEvent.click(screen.getByRole('checkbox', { name: 'Pippo' }))
    confirmOpenRow('Done')
    clickCreateSave()

    await waitFor(() => expect(createUserMock).toHaveBeenCalledTimes(1))
    expect(createUserMock.mock.calls[0][0].custom_fields).toEqual({ pippo: true })
  })

  it('seeds the custom field from the loaded user and PATCHes it alone', async () => {
    await renderDetail(userFixture({ permissions: PIPPO_PERMISSIONS, custom_fields: { pippo: true } }))

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Pippo' }))
    const checkbox = screen.getByRole('checkbox', { name: 'Pippo' })
    expect(checkbox).toBeChecked()
    fireEvent.click(checkbox)
    confirmOpenRow('Save')

    await waitFor(() => expect(updateUserMock).toHaveBeenCalledWith(7, { custom_fields: { pippo: false } }))
    expect(pencilOf('Email')).toBeInTheDocument()
  })
})
