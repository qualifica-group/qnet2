import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RoleForm } from '@/features/roles/role-form'
import type { RoleDetailWithPermissions } from '@/features/roles/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'

/**
 * Spec 0021: the roles module wires the universal custom-fields renderer
 * (mirrors `company-form-custom-fields.test.tsx`) — mounting
 * `<CustomFieldsSection>` is the only roles-specific integration.
 */

const createRoleMock = vi.fn()
const updateRoleMock = vi.fn()

vi.mock('@/features/roles/api', () => ({
  createRole: (...args: unknown[]) => createRoleMock(...args),
  updateRole: (...args: unknown[]) => updateRoleMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

// Not about the spec 0076 permission explorer (covered by
// `permission-explorer/permission-explorer.test.tsx`): an empty catalogue
// keeps the section out of the way of the custom-fields assertions below.
vi.mock('@/features/roles/permission-catalogue-api', () => ({
  fetchPermissionCatalogue: () => Promise.resolve({ areas: [] }),
}))

// Replace the async users multi-select with a lightweight stub so this suite
// focuses on the custom-fields wiring, not the network-backed select.
vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({ value }: { value: number[] }) => (
    <div data-testid="users-value">{value.join(',')}</div>
  ),
}))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const FULL_ACCESS: ResourcePermissions['resource'] = {
  view: true,
  create: true,
  update: true,
  delete: true,
  export: true,
  import: true,
}

const NOTES_FIELD: CustomFieldDescriptor = {
  key: 'custom.notes',
  type: 'text',
  label: 'Notes',
  group: null,
  mandatory: false,
  source: 'custom',
}

function permissionsWithNotes(): ResourcePermissions {
  return {
    resource: FULL_ACCESS,
    fields: {
      'custom.notes': {
        visible: true,
        hidden: false,
        editable: true,
        readonly: false,
        required: false,
        disabled: false,
      },
    },
    actions: {},
  }
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
    permissions: [],
    created_at: null,
    field_permissions: [],
    authorization: permissionsWithNotes(),
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
  fetchResourceMetaMock.mockResolvedValue({ fields: [NOTES_FIELD], permissions: permissionsWithNotes() })
})

describe('RoleForm — custom fields (spec 0021)', () => {
  it('renders the resource custom field control in create mode', async () => {
    render(
      <RoleForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('textbox', { name: 'Notes' })).toBeInTheDocument()
  })

  it('includes the valued custom field in the create payload', async () => {
    createRoleMock.mockResolvedValue(role())
    const onSuccess = vi.fn()

    render(
      <RoleForm
        mode={{ type: 'create' }}
        onSuccess={onSuccess}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(await screen.findByLabelText(/^Name/), { target: { value: 'Editor' } })
    fireEvent.change(await screen.findByRole('textbox', { name: 'Notes' }), {
      target: { value: 'Key role' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createRoleMock).toHaveBeenCalledTimes(1))
    const payload = createRoleMock.mock.calls[0][0]
    expect(payload.custom_fields).toEqual({ notes: 'Key role' })
  })

  it('seeds the custom field value from the loaded role detail in edit mode', async () => {
    render(
      <RoleForm
        mode={{ type: 'edit', role: role({ custom_fields: { notes: 'Existing note' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('textbox', { name: 'Notes' })).toHaveValue('Existing note')
  })
})
