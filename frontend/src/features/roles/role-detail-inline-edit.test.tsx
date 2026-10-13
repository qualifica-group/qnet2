import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, AxiosHeaders } from 'axios'
import i18n from '@/i18n'
import { updateRole } from '@/features/roles/api'
import { RoleDetailView } from '@/features/roles/role-detail'
import type { PermissionCatalogue } from '@/features/roles/permission-catalogue-api'
import type { RoleDetailWithPermissions } from '@/features/roles/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'

/**
 * Spec 0230: the role detail edits in place, one row at a time. A row PATCHes
 * its own field alone; the "Permessi" block row PATCHes the permissions and
 * the field-permission matrix, nothing else.
 */

vi.mock('@/features/roles/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/roles/api')>()),
  updateRole: vi.fn(),
}))
vi.mock('@/features/activity-log/activity-log-section', () => ({ ActivityLogSection: () => null }))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const fetchPermissionCatalogueMock = vi.fn<() => Promise<PermissionCatalogue>>()
vi.mock('@/features/roles/permission-catalogue-api', () => ({
  fetchPermissionCatalogue: () => fetchPermissionCatalogueMock(),
}))

// The members' names come from the for-select endpoint; no network in this suite.
vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: () =>
    Promise.resolve({
      items: [{ id: 11, label: 'Mario Rossi' }],
      pagination: { offset: 0, limit: 25, total: 1, has_more: false },
    }),
}))

// A controllable stand-in for the network-backed users multi-select.
vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({ value, onChange }: { value: number[]; onChange: (value: number[]) => void }) => (
    <div>
      <button type="button" onClick={() => onChange([...value, 7])}>
        add-user-7
      </button>
    </div>
  ),
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

const EDITABLE_FIELD = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}

const CATALOGUE: PermissionCatalogue = {
  areas: [
    {
      key: 'administration',
      label_key: 'navigation.users',
      resources: [
        {
          resource: 'users',
          label_key: 'navigation.users',
          permissions: [
            { name: 'users.viewAny', ability: 'viewAny' },
            { name: 'users.view', ability: 'view' },
          ],
          fields: [{ key: 'personal_data.tax_code', type: 'text', group: 'personal_data', mandatory: false, custom: false, label: null }],
        },
      ],
    },
  ],
}

function role(overrides: Partial<RoleDetailWithPermissions> = {}): RoleDetailWithPermissions {
  return {
    id: 3,
    name: 'Editor',
    permissions: ['users.viewAny'],
    users: [11],
    created_at: '2026-01-01T00:00:00Z',
    field_permissions: [],
    authorization: { resource: FULL_ACCESS, fields: {}, actions: {} },
    ...overrides,
  }
}

function renderDetail(detail: RoleDetailWithPermissions) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(<RoleDetailView role={detail} />, {
    wrapper: ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>,
  })
}

const label = (key: string) => i18n.t(key)
const pencilOf = (field: string) => screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field }) })
const save = () => fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.save') }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(updateRole).mockReset()
  vi.mocked(updateRole).mockImplementation(async () => role())
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: { resource: FULL_ACCESS, fields: {}, actions: {} } })
  fetchPermissionCatalogueMock.mockReset()
  fetchPermissionCatalogueMock.mockResolvedValue(CATALOGUE)
})

describe('RoleDetailView — header rows (spec 0230 D-2)', () => {
  it('has no Edit button: the rows are closed, each with its pencil', () => {
    renderDetail(role())

    expect(screen.queryByRole('button', { name: /^Edit$/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf('Name')).toBeInTheDocument()
    expect(pencilOf('Members')).toBeInTheDocument()
    expect(pencilOf('Permissions')).toBeInTheDocument()
  })

  it('names the members on the closed row', async () => {
    renderDetail(role())

    expect(await screen.findByText('Mario Rossi')).toBeInTheDocument()
  })

  it('Save sends the edited name alone', async () => {
    renderDetail(role())

    fireEvent.click(pencilOf('Name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    save()

    await waitFor(() => expect(updateRole).toHaveBeenCalledWith(3, { name: 'Renamed' }))
  })

  it('Save sends the members alone, without the name or the permissions', async () => {
    renderDetail(role())

    fireEvent.click(pencilOf('Members') as HTMLElement)
    fireEvent.click(screen.getByText('add-user-7'))
    save()

    await waitFor(() => expect(updateRole).toHaveBeenCalledWith(3, { users: [11, 7] }))
  })

  it('Cancel restores the row without calling the API', () => {
    renderDetail(role())

    fireEvent.click(pencilOf('Name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.cancel') }))

    expect(screen.queryByText('Renamed')).not.toBeInTheDocument()
    expect(updateRole).not.toHaveBeenCalled()
  })

  it('Save on an unchanged row closes it without calling the API', async () => {
    renderDetail(role())

    fireEvent.click(pencilOf('Name') as HTMLElement)
    save()

    await waitFor(() => expect(screen.queryByRole('textbox')).not.toBeInTheDocument())
    expect(updateRole).not.toHaveBeenCalled()
  })

  it('shows a server refusal of the name inline, on the open row', async () => {
    vi.mocked(updateRole).mockRejectedValueOnce(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        statusText: 'Unprocessable',
        headers: {},
        config: { headers: new AxiosHeaders() },
        data: { success: false, message: 'Validation failed', errors: { name: ['field not editable'] } },
      }),
    )
    renderDetail(role())

    fireEvent.click(pencilOf('Name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    save()

    expect(await screen.findByText('field not editable')).toBeInTheDocument()
    expect(updateRole).toHaveBeenCalledTimes(1)
  })

  it('offers no pencil without the update permission', () => {
    renderDetail(
      role({
        authorization: {
          resource: { ...FULL_ACCESS, update: false, create: false, delete: false },
          fields: {},
          actions: {},
        },
      }),
    )

    expect(pencilOf('Name')).not.toBeInTheDocument()
    expect(pencilOf('Members')).not.toBeInTheDocument()
    expect(pencilOf('Permissions')).not.toBeInTheDocument()
  })

  it('offers no pencil on a field the role cannot edit (super-admin name), the others stay editable', () => {
    renderDetail(
      role({
        name: 'super-admin',
        authorization: {
          resource: FULL_ACCESS,
          fields: {
            name: { ...EDITABLE_FIELD, editable: false, readonly: true },
            permissions: { ...EDITABLE_FIELD, editable: false, readonly: true },
          },
          actions: {},
        },
      }),
    )

    expect(pencilOf('Name')).not.toBeInTheDocument()
    expect(pencilOf('Permissions')).not.toBeInTheDocument()
    expect(pencilOf('Members')).toBeInTheDocument()
  })
})

describe('RoleDetailView — the permissions block row (spec 0230 D-3)', () => {
  it('opens the explorer and Save sends the permissions alone', async () => {
    renderDetail(role())

    fireEvent.click(pencilOf('Permissions') as HTMLElement)
    fireEvent.click(await screen.findByRole('checkbox', { name: label('roles.form.selectAllGlobal') }))
    save()

    await waitFor(() => expect(updateRole).toHaveBeenCalledTimes(1))
    const [id, payload] = vi.mocked(updateRole).mock.calls[0]
    expect(id).toBe(3)
    expect(Object.keys(payload)).toEqual(['permissions'])
    expect([...(payload.permissions ?? [])].sort()).toEqual(['users.view', 'users.viewAny'])
  })

  it('seeds the matrix from the saved role and sends the field permissions alone', async () => {
    renderDetail(
      role({
        field_permissions: [
          { resource: 'users', field: 'personal_data.tax_code', visible: false, editable: true, required: false },
        ],
      }),
    )

    fireEvent.click(pencilOf('Permissions') as HTMLElement)
    const visible = await screen.findByRole('checkbox', { name: 'Tax code — Visible' })
    expect(visible).not.toBeChecked()
    fireEvent.click(visible)
    save()

    await waitFor(() => expect(updateRole).toHaveBeenCalledTimes(1))
    expect(vi.mocked(updateRole).mock.calls[0][1]).toEqual({
      field_permissions: [
        { resource: 'users', field: 'personal_data.tax_code', visible: true, editable: true, required: false },
      ],
    })
  })

  it('Cancel closes the explorer without calling the API', async () => {
    renderDetail(role())

    fireEvent.click(pencilOf('Permissions') as HTMLElement)
    fireEvent.click(await screen.findByRole('checkbox', { name: label('roles.form.selectAllGlobal') }))
    fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.cancel') }))

    expect(screen.queryByRole('checkbox', { name: label('roles.form.selectAllGlobal') })).not.toBeInTheDocument()
    expect(updateRole).not.toHaveBeenCalled()
  })
})

describe('RoleDetailView — custom fields (spec 0021)', () => {
  it('a custom field row saves its own value alone', async () => {
    const authorization = {
      resource: FULL_ACCESS,
      fields: { 'custom.notes': EDITABLE_FIELD },
      actions: {},
    }
    fetchResourceMetaMock.mockResolvedValue({ fields: [NOTES_FIELD], permissions: authorization })
    renderDetail(role({ authorization, custom_fields: { notes: 'Existing note' } }))

    fireEvent.click(await screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Notes' }) }))
    const notes = await screen.findByRole('textbox', { name: 'Notes' })
    expect(notes).toHaveValue('Existing note')
    fireEvent.change(notes, { target: { value: 'Key role' } })
    save()

    await waitFor(() => expect(updateRole).toHaveBeenCalledWith(3, { custom_fields: { notes: 'Key role' } }))
  })
})
