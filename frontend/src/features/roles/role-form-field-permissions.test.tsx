import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RoleForm } from '@/features/roles/role-form'
import type { RoleDetailWithPermissions } from '@/features/roles/types'
import type { PermissionCatalogue } from '@/features/roles/permission-catalogue-api'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * Acceptance criteria 11-15 (spec 0006), rehomed into the module scheda by
 * spec 0076: the per-role field-permission matrix — renders from the
 * catalogue's selected module (default: the first one), toggles update form
 * state and the submit payload, edit mode seeds + round-trips, a server 422
 * on a `field_permissions.*` key surfaces inline, and the section is itself
 * gated by the role form's own metadata (0004 `resource.update`/`create`).
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

const fetchPermissionCatalogueMock = vi.fn<() => Promise<PermissionCatalogue>>()
vi.mock('@/features/roles/permission-catalogue-api', () => ({
  fetchPermissionCatalogue: () => fetchPermissionCatalogueMock(),
}))

// Replace the async users multi-select with a lightweight stub so this suite
// focuses on the field-permission matrix, not the network-backed select
// (covered by its own test).
vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({ value }: { value: number[] }) => (
    <div data-testid="users-value">{value.join(',')}</div>
  ),
}))

/** Create-context authorization block: full resource abilities, no field/action overrides. */
function fullAccessMeta(): ResourceMeta {
  return {
    fields: [],
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
  }
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

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

// A single-module catalogue with `users` as its only (and so default-selected)
// module. A non-mandatory field is the toggling/default-state subject
// throughout this suite (spec 0008 follow-up: `mandatory` fields render
// locked checked+disabled, so they can no longer stand in for "unrestricted,
// toggable" assertions).
function catalogueWithUsersField(field: { key: string; type: string; group: string | null; mandatory: boolean }): PermissionCatalogue {
  return {
    areas: [
      {
        key: 'administration',
        label_key: 'navigation.users',
        resources: [
          {
            resource: 'users',
            label_key: 'navigation.users',
            permissions: [{ name: 'users.viewAny', ability: 'viewAny' }],
            fields: [{ ...field, custom: false, label: null }],
          },
        ],
      },
    ],
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createRoleMock.mockReset()
  updateRoleMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchPermissionCatalogueMock.mockReset()
})

describe('RoleForm — field-permission matrix (spec 0006/0076)', () => {
  it('AC11 — renders the matrix from the catalogue: one row per field, three toggles each', async () => {
    fetchResourceMetaMock.mockResolvedValue(fullAccessMeta())
    fetchPermissionCatalogueMock.mockResolvedValue(
      catalogueWithUsersField({ key: 'personal_data.tax_code', type: 'text', group: 'personal_data', mandatory: false }),
    )

    render(
      <RoleForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    // `users` is the catalogue's only module, so it is selected by default.
    expect(await screen.findByText('Fields')).toBeInTheDocument()

    // Default (no row yet) = unrestricted: visible + editable, not required.
    await waitFor(() =>
      expect(screen.getByRole('checkbox', { name: 'Tax code — Visible' })).toBeInTheDocument(),
    )
    expect(screen.getByRole('checkbox', { name: 'Tax code — Visible' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Tax code — Editable' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Tax code — Required' })).not.toBeChecked()
  })

  it('AC12 — toggling a cell updates form state; submit includes the field_permissions array', async () => {
    fetchResourceMetaMock.mockResolvedValue(fullAccessMeta())
    fetchPermissionCatalogueMock.mockResolvedValue(
      catalogueWithUsersField({ key: 'personal_data.tax_code', type: 'text', group: 'personal_data', mandatory: false }),
    )
    createRoleMock.mockResolvedValue(role())

    render(
      <RoleForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await waitFor(() =>
      expect(screen.getByRole('checkbox', { name: 'Tax code — Visible' })).toBeInTheDocument(),
    )
    fireEvent.click(screen.getByRole('checkbox', { name: 'Tax code — Visible' }))
    expect(screen.getByRole('checkbox', { name: 'Tax code — Visible' })).not.toBeChecked()

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Support' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createRoleMock).toHaveBeenCalled())
    const payload = createRoleMock.mock.calls[0][0]
    expect(payload.field_permissions).toEqual([
      { resource: 'users', field: 'personal_data.tax_code', visible: false, editable: true, required: false },
    ])
  })

  it('AC13 — edit mode seeds the matrix from role.field_permissions; unchanged submit round-trips the same set', async () => {
    fetchPermissionCatalogueMock.mockResolvedValue(
      catalogueWithUsersField({ key: 'personal_data.tax_code', type: 'text', group: 'personal_data', mandatory: false }),
    )
    const seeded = role({
      field_permissions: [
        { resource: 'users', field: 'personal_data.tax_code', visible: false, editable: true, required: false },
      ],
    })
    updateRoleMock.mockResolvedValue(seeded)

    render(
      <RoleForm mode={{ type: 'edit', role: seeded }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    // Seeded from `role.field_permissions`: the visible flag was restricted.
    await waitFor(() =>
      expect(screen.getByRole('checkbox', { name: 'Tax code — Visible' })).not.toBeChecked(),
    )
    expect(screen.getByRole('checkbox', { name: 'Tax code — Editable' })).toBeChecked()

    // Submit unchanged: the matrix round-trips (payload omits the key,
    // mirroring the existing `permissions`/`users` diff convention).
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRoleMock).toHaveBeenCalled())
    const [, payload] = updateRoleMock.mock.calls[0]
    expect('field_permissions' in payload).toBe(false)
  })

  it('AC14 — a server 422 on a field_permissions.* key surfaces inline', async () => {
    fetchPermissionCatalogueMock.mockResolvedValue(
      catalogueWithUsersField({ key: 'personal_data.tax_code', type: 'text', group: 'personal_data', mandatory: false }),
    )
    updateRoleMock.mockRejectedValue(
      new AxiosError(
        'Unprocessable',
        '422',
        undefined,
        undefined,
        {
          status: 422,
          data: {
            success: false,
            message: 'Validation failed',
            errors: { field_permissions: ['field not editable'] },
          },
        } as never,
      ),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <RoleForm mode={{ type: 'edit', role: role() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await waitFor(() =>
      expect(screen.getByRole('checkbox', { name: 'Tax code — Visible' })).toBeInTheDocument(),
    )
    fireEvent.click(screen.getByRole('checkbox', { name: 'Tax code — Visible' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('field not editable')).toBeInTheDocument())
    expect(updateRoleMock).toHaveBeenCalledTimes(1)

    vi.restoreAllMocks()
  })

  it('a mandatory field (spec 0008) renders its three checkboxes checked and disabled', async () => {
    fetchResourceMetaMock.mockResolvedValue(fullAccessMeta())
    fetchPermissionCatalogueMock.mockResolvedValue(
      catalogueWithUsersField({ key: 'email', type: 'email', group: null, mandatory: true }),
    )

    render(
      <RoleForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await waitFor(() =>
      expect(screen.getByRole('checkbox', { name: 'Email — Visible' })).toBeInTheDocument(),
    )
    expect(screen.getByRole('checkbox', { name: 'Email — Visible' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Email — Visible' })).toBeDisabled()
    expect(screen.getByRole('checkbox', { name: 'Email — Editable' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Email — Editable' })).toBeDisabled()
    expect(screen.getByRole('checkbox', { name: 'Email — Required' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Email — Required' })).toBeDisabled()
  })

  it('AC15 — the fields subsection is hidden when the role form metadata denies write access', async () => {
    fetchPermissionCatalogueMock.mockResolvedValue(
      catalogueWithUsersField({ key: 'personal_data.tax_code', type: 'text', group: 'personal_data', mandatory: false }),
    )

    render(
      <RoleForm
        mode={{
          type: 'edit',
          role: role({
            authorization: {
              resource: { view: true, create: true, update: false, delete: false, export: true, import: false },
              fields: {},
              actions: {},
            },
          }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    // The module's actions still render (a separate, non-field-permission gate)...
    expect(await screen.findByText('Actions')).toBeInTheDocument()
    // ...but its fields subsection does not.
    expect(screen.queryByText('Fields')).not.toBeInTheDocument()
    expect(screen.queryByRole('checkbox', { name: 'Tax code — Visible' })).not.toBeInTheDocument()
  })
})
