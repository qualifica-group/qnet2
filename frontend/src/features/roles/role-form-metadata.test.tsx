import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RoleForm } from '@/features/roles/role-form'
import type { RoleDetailWithPermissions } from '@/features/roles/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * Acceptance criteria 11-16 (spec 0004): the metadata-driven behaviour of the
 * form (hidden fields, readonly/disabled fields, required labels, edit-seeded
 * permissions + 422 mapping, graceful fallback). AC14 (gated action
 * affordance) does not apply here — the role form has no action buttons of
 * its own (covered instead by `user-form-metadata.test.tsx`, avatar
 * upload/remove). The roles-CRUD behaviour itself (payload diffing, members
 * hydration, …) is covered by `role-form.test.tsx`.
 */

const createRoleMock = vi.fn()
const updateRoleMock = vi.fn()

vi.mock('@/features/roles/api', () => ({
  createRole: (...args: unknown[]) => createRoleMock(...args),
  updateRole: (...args: unknown[]) => updateRoleMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

// This suite is not about the spec 0076 permission explorer (covered by
// `permission-explorer/permission-explorer.test.tsx`): an empty catalogue
// keeps the new section out of the way of the 0004 assertions below.
vi.mock('@/features/roles/permission-catalogue-api', () => ({
  fetchPermissionCatalogue: () => Promise.resolve({ areas: [] }),
}))

// Replace the async users multi-select with a lightweight stub so this suite
// focuses on the form's metadata wiring, not the network-backed select
// (covered by its own test).
vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({ value }: { value: number[] }) => (
    <div data-testid="users-value">{value.join(',')}</div>
  ),
}))

/** The `<label>` element whose text starts with `text` (exact-match helper). */
function labelFor(text: string): HTMLElement {
  return screen.getByText(
    (_, element) => element?.tagName === 'LABEL' && element.textContent?.startsWith(text) === true,
  )
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function role(overrides: Partial<RoleDetailWithPermissions> = {}): RoleDetailWithPermissions {
  return {
    id: 3,
    name: 'Editor',
    permissions: ['users.viewAny'],
    created_at: null,
    field_permissions: [],
    authorization: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createRoleMock.mockReset()
  updateRoleMock.mockReset()
  fetchResourceMetaMock.mockReset()
})

describe('RoleForm — metadata-driven authorization (spec 0004)', () => {
  it('AC11/13 — hides a hidden field, marks a required field from create-context metadata', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
        fields: {
          name: {
            visible: true, hidden: false, editable: true, readonly: false, required: true, disabled: false,
          },
          permissions: {
            visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: false,
          },
          users: {
            visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false,
          },
        },
        actions: {},
      },
    })

    render(
      <RoleForm
        mode={{ type: 'create' }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    // AC11: the hidden field (and everything it would have rendered) is
    // absent from the DOM.
    await waitFor(() => expect(screen.getByLabelText(/^Name/)).toBeInTheDocument())
    expect(screen.queryByText('Permissions')).not.toBeInTheDocument()
    expect(screen.queryByText('Select all permissions')).not.toBeInTheDocument()

    // AC13: `required` from metadata drives the label's `*` — name is
    // required, the (visible) members field is not.
    expect(labelFor('Name').textContent).toContain('*')
    expect(labelFor('Members').textContent).not.toContain('*')
  })

  it('AC12 — edit mode renders a readonly/non-editable field (super-admin role name) disabled', () => {
    render(
      <RoleForm
        mode={{
          type: 'edit',
          role: role({
            name: 'super-admin',
            authorization: {
              resource: { view: true, create: true, update: false, delete: false, export: true, import: false },
              fields: {
                name: {
                  visible: true, hidden: false, editable: false, readonly: true, required: false, disabled: false,
                },
              },
              actions: {},
            },
          }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    const name = screen.getByLabelText(/^Name/)
    expect(name).toBeDisabled()
    expect(name).toHaveAttribute('readonly')
  })

  it('AC16 — falls back to visible+editable when a field is missing from metadata', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
        fields: {
          // `name` intentionally absent from the metadata.
          permissions: {
            visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false,
          },
        },
        actions: {},
      },
    })

    render(
      <RoleForm
        mode={{ type: 'create' }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    // No crash, and the field renders visible + editable (the graceful default).
    await waitFor(() => expect(screen.getByLabelText(/^Name/)).toBeInTheDocument())
    expect(screen.getByLabelText(/^Name/)).toBeEnabled()
  })

  it('AC15 — seeds permissions from the loaded detail and surfaces a 422 field error inline', async () => {
    updateRoleMock.mockRejectedValue(
      new AxiosError(
        'Unprocessable',
        '422',
        undefined,
        undefined,
        {
          status: 422,
          data: { success: false, message: 'Validation failed', errors: { name: ['field not editable'] } },
        } as never,
      ),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <RoleForm
        mode={{ type: 'edit', role: role() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('field not editable')).toBeInTheDocument())
    expect(updateRoleMock).toHaveBeenCalledTimes(1)

    vi.restoreAllMocks()
  })
})
