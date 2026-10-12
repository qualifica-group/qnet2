import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RoleForm } from '@/features/roles/role-form'
import type { RoleDetailWithPermissions } from '@/features/roles/types'
import type { ResourcePermissions } from '@/features/authorization/types'

const createRole = vi.fn()

vi.mock('@/features/roles/api', () => ({
  createRole: (...args: unknown[]) => createRole(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/**
 * This suite is not about authorization metadata (covered by
 * `role-form-metadata.test.tsx`): every field resolves as visible+editable
 * (the `MetaField` fallback, since `fields` is empty). The detail's in-place
 * editing is covered by `role-detail-inline-edit.test.tsx`.
 */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

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
})

function createdRole(): RoleDetailWithPermissions {
  return {
    id: 3,
    name: 'Support',
    permissions: [],
    created_at: null,
    field_permissions: [],
    authorization: FULL_ACCESS_PERMISSIONS,
  }
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

const findPencilOf = (field: string) =>
  screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field }) })

describe('RoleForm — a replica of the detail (spec 0230 D-4)', () => {
  it('keeps the Dettagli rows closed until clicked', async () => {
    render(<RoleForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    expect(await findPencilOf('Name')).toBeInTheDocument()
    expect(screen.queryByLabelText(/^Name/)).not.toBeInTheDocument()
    expect(screen.queryByTestId('users-value')).not.toBeInTheDocument()
  })

  it('submits the name and the selected users when creating a role', async () => {
    createRole.mockResolvedValue(createdRole())
    const onSuccess = vi.fn()

    render(<RoleForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fireEvent.click(await findPencilOf('Name'))
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Support' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.apply') }))
    fireEvent.click(await findPencilOf('Members'))
    fireEvent.click(screen.getByText('add-user-7'))
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.apply') }))
    fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

    await waitFor(() => expect(createRole).toHaveBeenCalled())
    expect(createRole).toHaveBeenCalledWith(expect.objectContaining({ name: 'Support', users: [7] }))
    await waitFor(() => expect(onSuccess).toHaveBeenCalled())
  })

  it('Cancel leaves the form without calling the API', async () => {
    const onCancel = vi.fn()
    render(<RoleForm onSuccess={vi.fn()} onCancel={onCancel} />, { wrapper: wrapper() })

    fireEvent.click((await screen.findAllByRole('button', { name: 'Cancel' }))[0])

    expect(onCancel).toHaveBeenCalled()
    expect(createRole).not.toHaveBeenCalled()
  })
})
