import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
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
 * state and the create payload, and the subsection is itself gated by the
 * form's own metadata (0004 `resource.create`). Seeding from a saved role and
 * the 422 mapping live in `role-detail-inline-edit.test.tsx` (spec 0230).
 */

const createRoleMock = vi.fn()

vi.mock('@/features/roles/api', () => ({
  createRole: (...args: unknown[]) => createRoleMock(...args),
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
      <RoleForm onSuccess={vi.fn()} onCancel={vi.fn()} />,
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
      <RoleForm onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await waitFor(() =>
      expect(screen.getByRole('checkbox', { name: 'Tax code — Visible' })).toBeInTheDocument(),
    )
    fireEvent.click(screen.getByRole('checkbox', { name: 'Tax code — Visible' }))
    expect(screen.getByRole('checkbox', { name: 'Tax code — Visible' })).not.toBeChecked()

    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Name' }) }))
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Support' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.apply') }))
    fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

    await waitFor(() => expect(createRoleMock).toHaveBeenCalled())
    const payload = createRoleMock.mock.calls[0][0]
    expect(payload.field_permissions).toEqual([
      { resource: 'users', field: 'personal_data.tax_code', visible: false, editable: true, required: false },
    ])
  })

  it('a mandatory field (spec 0008) renders its three checkboxes checked and disabled', async () => {
    fetchResourceMetaMock.mockResolvedValue(fullAccessMeta())
    fetchPermissionCatalogueMock.mockResolvedValue(
      catalogueWithUsersField({ key: 'email', type: 'email', group: null, mandatory: true }),
    )

    render(
      <RoleForm onSuccess={vi.fn()} onCancel={vi.fn()} />,
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

  it('AC15 — the fields subsection is hidden when the form metadata denies the create ability', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        resource: { view: true, create: false, update: false, delete: false, export: true, import: false },
        fields: {},
        actions: {},
      },
    })
    fetchPermissionCatalogueMock.mockResolvedValue(
      catalogueWithUsersField({ key: 'personal_data.tax_code', type: 'text', group: 'personal_data', mandatory: false }),
    )

    render(<RoleForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    // The module's actions still render (a separate, non-field-permission gate)...
    expect(await screen.findByText('Actions')).toBeInTheDocument()
    // ...but its fields subsection does not.
    expect(screen.queryByText('Fields')).not.toBeInTheDocument()
    expect(screen.queryByRole('checkbox', { name: 'Tax code — Visible' })).not.toBeInTheDocument()
  })
})
