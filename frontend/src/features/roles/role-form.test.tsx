import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RoleForm } from '@/features/roles/role-form'
import type { RoleDetailWithPermissions } from '@/features/roles/types'
import type { ResourcePermissions } from '@/features/authorization/types'

const createRole = vi.fn()
const updateRole = vi.fn()

vi.mock('@/features/roles/api', () => ({
  createRole: (...args: unknown[]) => createRole(...args),
  updateRole: (...args: unknown[]) => updateRole(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/**
 * This suite is not about authorization metadata (covered by
 * `role-form-metadata.test.tsx`): every field resolves as visible+editable
 * (the `MetaField` fallback, since `fields` is empty) so create/edit render
 * exactly as they did before spec 0004.
 */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/roles/use-role-form-meta', () => ({
  useRoleFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

// `useRoleForm` reads `/meta/roles` (spec 0021) to build the dynamic
// custom-fields schema; this suite has no custom fields to exercise (covered
// by `role-form-custom-fields.test.tsx`), so it resolves to an empty catalogue.
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => Promise.resolve({ fields: [], permissions: FULL_ACCESS_PERMISSIONS }),
}))

// This suite is not about the spec 0076 permission explorer (covered by
// `permission-explorer/permission-explorer.test.tsx`): an empty catalogue
// keeps the new section out of the way of the users-integration assertions
// below.
vi.mock('@/features/roles/permission-catalogue-api', () => ({
  fetchPermissionCatalogue: () => Promise.resolve({ areas: [] }),
}))

// Replace the async users multi-select with a lightweight controllable stub so
// the integration test focuses on the form's payload/diffing logic, not the
// network-backed select (covered by its own test).
vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({
    value,
    onChange,
  }: {
    value: number[]
    onChange: (value: number[]) => void
  }) => (
    <div>
      <span data-testid="users-value">{value.join(',')}</span>
      <button type="button" onClick={() => onChange([...value, 7])}>
        add-user-7
      </button>
      <button type="button" onClick={() => onChange([])}>
        clear-users
      </button>
    </div>
  ),
}))


beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createRole.mockReset()
  updateRole.mockReset()
})

function editRole(
  overrides: Partial<RoleDetailWithPermissions> = {},
): RoleDetailWithPermissions {
  return {
    id: 3,
    name: 'Editor',
    permissions: ['users.viewAny'],
    created_at: null,
    field_permissions: [],
    // `useRoleFormMeta` is mocked above, so this value is never actually read;
    // present only to satisfy `RoleFormMode`'s edit-variant type.
    authorization: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

describe('RoleForm — users integration', () => {
  it('submits the selected users when creating a role', async () => {
    createRole.mockResolvedValue(editRole())
    const onSuccess = vi.fn()

    render(
      <RoleForm
        mode={{ type: 'create' }}
        onSuccess={onSuccess}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), {
      target: { value: 'Support' },
    })
    fireEvent.click(screen.getByText('add-user-7'))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createRole).toHaveBeenCalled())
    expect(createRole).toHaveBeenCalledWith(
      expect.objectContaining({ name: 'Support', users: [7] }),
    )
    await waitFor(() => expect(onSuccess).toHaveBeenCalled())
  })

  it('hydrates current members in edit mode from role.users', () => {
    render(
      <RoleForm
        mode={{ type: 'edit', role: editRole({ users: [11, 22] }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )
    expect(screen.getByTestId('users-value')).toHaveTextContent('11,22')
  })

  it('omits users from the PATCH payload when membership is unchanged', async () => {
    updateRole.mockResolvedValue(editRole({ users: [11] }))

    render(
      <RoleForm
        mode={{ type: 'edit', role: editRole({ users: [11] }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    // Change only the name; leave members as the hydrated [11].
    fireEvent.change(screen.getByLabelText(/^Name/), {
      target: { value: 'Editor 2' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRole).toHaveBeenCalled())
    const [, payload] = updateRole.mock.calls[0]
    expect(payload).toEqual({ name: 'Editor 2' })
    expect('users' in payload).toBe(false)
  })

  it('includes users in the PATCH payload only when membership changed', async () => {
    updateRole.mockResolvedValue(editRole({ users: [] }))

    render(
      <RoleForm
        mode={{ type: 'edit', role: editRole({ users: [11] }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByText('clear-users'))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRole).toHaveBeenCalled())
    const [, payload] = updateRole.mock.calls[0]
    expect(payload).toEqual({ users: [] })
  })
})
