import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { fireEvent, render as renderWithoutProviders, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { BusinessFunctionDetailView } from '@/features/business-functions/business-function-detail'
import type { BusinessFunctionDetailWithPermissions } from '@/features/business-functions/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'

/**
 * Spec 0237: the detail shows the name, type, relations, custom fields and
 * creation date; every row edits in place (wave 3 of the "no edit pages"
 * plan), each save a PATCH of that field alone. The Activity Log gate is
 * exercised on the `permissions.actions.view_activity` prop.
 */

const activityLogSectionMock = vi.fn()
const updateBusinessFunctionMock = vi.fn()

vi.mock('@/features/business-functions/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/business-functions/api')>()),
  updateBusinessFunction: (...args: unknown[]) => updateBusinessFunctionMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

// Related-record links open their target through `useModuleOpener`, whose mode
// resolver reads the user's preference, and render only for an actor who can
// view the target module: neither is under test here.
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/business-functions/business-function-test-stubs')).AsyncPaginatedSelectStub,
}))
vi.mock('@/components/ui/async-paginated-multi-select', async () => ({
  AsyncPaginatedMultiSelect: (await import('@/features/business-functions/business-function-test-stubs'))
    .AsyncPaginatedMultiSelectStub,
}))

const NOTES_FIELD: CustomFieldDescriptor = {
  key: 'custom.notes',
  type: 'text',
  label: 'Notes',
  group: null,
  mandatory: false,
  source: 'custom',
}

const EDITABLE = { visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false }

function permissions(overrides: Partial<ResourcePermissions> = {}): ResourcePermissions {
  return {
    resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
    fields: { 'custom.notes': EDITABLE },
    actions: { view_activity: false },
    ...overrides,
  }
}

function businessFunction(overrides: Partial<BusinessFunctionDetailWithPermissions> = {}): BusinessFunctionDetailWithPermissions {
  return {
    id: 1,
    name: 'Engineering',
    is_business_unit: true,
    is_business_service: false,
    type: 'business_unit',
    manager_id: 10,
    manager: { id: 10, name: 'Ada Lovelace', avatar_url: null },
    user_ids: [20, 21],
    users: [
      { id: 20, name: 'Grace Hopper', avatar_url: null },
      { id: 21, name: 'Katherine Johnson', avatar_url: null },
    ],
    parent_id: 5,
    parent: { id: 5, name: 'Operations' },
    operational_site_ids: [30],
    operational_sites: [{ id: 30, label: 'Via Roma 1 - Milano' }],
    company_ids: [4],
    companies: [{ id: 4, label: 'Acme Srl' }],
    created_at: '2026-01-15T10:30:00Z',
    custom_fields: { notes: 'Existing note' },
    permissions: permissions(),
    ...overrides,
  }
}

/** One QueryClient per test, inside a Router: the in-place rows save through React Query and the card links related records. */
function render(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return renderWithoutProviders(ui, {
    wrapper: ({ children }: { children: ReactNode }) => (
      <QueryClientProvider client={client}>
        <MemoryRouter>{children}</MemoryRouter>
      </QueryClientProvider>
    ),
  })
}

const label = (key: string) => i18n.t(`businessFunctions.form.${key}`)
const pencilOf = (field: string) =>
  screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field }) })
const openRow = (field: string) => fireEvent.click(pencilOf(field) as HTMLElement)
const saveRow = () => fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  activityLogSectionMock.mockReset()
  updateBusinessFunctionMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [NOTES_FIELD], permissions: permissions() })
})

describe('BusinessFunctionDetailView — detail fields', () => {
  it('shows the name, type, manager, users, parent, sites, custom field and creation date', async () => {
    render(<BusinessFunctionDetailView businessFunction={businessFunction()} />)

    expect(screen.getByRole('heading', { name: 'Engineering' })).toBeInTheDocument()
    expect(screen.getAllByText('Business Unit').length).toBeGreaterThan(0)
    expect(screen.getByText('AL')).toBeInTheDocument()
    expect(screen.getByText('Ada Lovelace')).toBeInTheDocument()
    expect(screen.getByText('Grace Hopper')).toBeInTheDocument()
    expect(screen.getByText('Katherine Johnson')).toBeInTheDocument()
    expect(screen.getByText('Operations')).toBeInTheDocument()
    expect(screen.getByText('Via Roma 1 - Milano')).toBeInTheDocument()
    expect(await screen.findByText('Existing note')).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-01-15T10:30:00Z'))).toBeInTheDocument()
  })

  it('renders "none" when the function has no type', () => {
    render(<BusinessFunctionDetailView businessFunction={businessFunction({ type: null })} />)

    expect(screen.getAllByText(label('type.none')).length).toBeGreaterThan(0)
  })

  it('shows the em dash for every empty relation', () => {
    render(
      <BusinessFunctionDetailView
        businessFunction={businessFunction({
          manager_id: null,
          manager: null,
          user_ids: [],
          users: [],
          parent_id: null,
          parent: null,
          operational_site_ids: [],
          operational_sites: [],
          company_ids: [],
          companies: [],
        })}
      />,
    )

    expect(screen.getAllByText('—')).toHaveLength(5)
  })
})

describe('BusinessFunctionDetailView — activity log section', () => {
  it('mounts the section for the viewed row when view_activity is granted', () => {
    render(
      <BusinessFunctionDetailView
        businessFunction={businessFunction({ permissions: permissions({ actions: { view_activity: true } }) })}
      />,
    )

    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'business-functions', id: 1 })
  })

  it('hides the section when view_activity is not granted', () => {
    render(<BusinessFunctionDetailView businessFunction={businessFunction()} />)

    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})

describe('BusinessFunctionDetailView — in-place editing', () => {
  it('has no Edit button: every row is closed, with its pencil', async () => {
    render(<BusinessFunctionDetailView businessFunction={businessFunction()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    for (const key of ['name', 'type.label', 'parent', 'manager', 'users', 'companies', 'operationalSites']) {
      expect(pencilOf(label(key))).toBeInTheDocument()
    }
    expect(await screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Notes' }) })).toBeInTheDocument()
  })

  it('Save sends only the changed field', async () => {
    updateBusinessFunctionMock.mockResolvedValue(businessFunction({ name: 'Renamed' }))
    render(<BusinessFunctionDetailView businessFunction={businessFunction()} />)

    openRow(label('name'))
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    saveRow()

    await waitFor(() => expect(updateBusinessFunctionMock).toHaveBeenCalledWith(1, { name: 'Renamed' }))
  })

  it('the type row saves only the type', async () => {
    updateBusinessFunctionMock.mockResolvedValue(businessFunction({ type: 'business_service' }))
    render(<BusinessFunctionDetailView businessFunction={businessFunction()} />)

    openRow(label('type.label'))
    fireEvent.click(screen.getByRole('combobox', { name: 'Type' }))
    fireEvent.click(screen.getByRole('option', { name: 'Business Service' }))
    saveRow()

    await waitFor(() => expect(updateBusinessFunctionMock).toHaveBeenCalledWith(1, { type: 'business_service' }))
  })

  it('the parent row saves only parent_id', async () => {
    updateBusinessFunctionMock.mockResolvedValue(businessFunction())
    render(<BusinessFunctionDetailView businessFunction={businessFunction()} />)

    openRow(label('parent'))
    fireEvent.click(screen.getByText('select-business-functions-50'))
    saveRow()

    await waitFor(() => expect(updateBusinessFunctionMock).toHaveBeenCalledWith(1, { parent_id: 50 }))
  })

  it('the manager row clears the responsible with a null', async () => {
    updateBusinessFunctionMock.mockResolvedValue(businessFunction({ manager_id: null, manager: null }))
    render(<BusinessFunctionDetailView businessFunction={businessFunction()} />)

    openRow(label('manager'))
    fireEvent.click(screen.getByText('clear-users'))
    saveRow()

    await waitFor(() => expect(updateBusinessFunctionMock).toHaveBeenCalledWith(1, { manager_id: null }))
  })

  it('the users row syncs the whole set', async () => {
    updateBusinessFunctionMock.mockResolvedValue(businessFunction())
    render(<BusinessFunctionDetailView businessFunction={businessFunction()} />)

    openRow(label('users'))
    fireEvent.click(screen.getByText('add-users-7'))
    saveRow()

    await waitFor(() => expect(updateBusinessFunctionMock).toHaveBeenCalledWith(1, { users: [20, 21, 7] }))
  })

  it('the sites row syncs the whole set', async () => {
    updateBusinessFunctionMock.mockResolvedValue(businessFunction())
    render(<BusinessFunctionDetailView businessFunction={businessFunction()} />)

    openRow(label('operationalSites'))
    fireEvent.click(screen.getByText('add-operational-sites-70'))
    saveRow()

    await waitFor(() => expect(updateBusinessFunctionMock).toHaveBeenCalledWith(1, { operational_sites: [30, 70] }))
  })

  it('shows the companies of the record', () => {
    render(<BusinessFunctionDetailView businessFunction={businessFunction()} />)

    expect(screen.getByText('Acme Srl')).toBeInTheDocument()
  })

  it('the companies row syncs the whole set', async () => {
    updateBusinessFunctionMock.mockResolvedValue(businessFunction())
    render(<BusinessFunctionDetailView businessFunction={businessFunction()} />)

    openRow(label('companies'))
    fireEvent.click(screen.getByText('add-companies-70'))
    saveRow()
    await waitFor(() => expect(updateBusinessFunctionMock).toHaveBeenCalledWith(1, { company_ids: [4, 70] }))
  })

  it('a custom field row saves only that custom field', async () => {
    updateBusinessFunctionMock.mockResolvedValue(businessFunction({ custom_fields: { notes: 'Updated note' } }))
    render(<BusinessFunctionDetailView businessFunction={businessFunction()} />)

    fireEvent.click(await screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Notes' }) }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Notes' }), { target: { value: 'Updated note' } })
    saveRow()

    await waitFor(() =>
      expect(updateBusinessFunctionMock).toHaveBeenCalledWith(1, { custom_fields: { notes: 'Updated note' } }),
    )
  })

  it('Cancel restores the row without calling the API', () => {
    render(<BusinessFunctionDetailView businessFunction={businessFunction()} />)

    openRow(label('name'))
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))

    expect(screen.queryByText('Renamed')).not.toBeInTheDocument()
    expect(updateBusinessFunctionMock).not.toHaveBeenCalled()
  })

  it('maps a server 422 onto the open row', async () => {
    updateBusinessFunctionMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )
    render(<BusinessFunctionDetailView businessFunction={businessFunction()} />)

    openRow(label('name'))
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Duplicate' } })
    saveRow()

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })

  it('offers no pencil when update is not allowed', async () => {
    render(
      <BusinessFunctionDetailView
        businessFunction={businessFunction({
          permissions: permissions({
            resource: { view: true, create: true, update: false, delete: true, export: true, import: true },
            fields: { 'custom.notes': { ...EDITABLE, editable: false, readonly: true } },
          }),
        })}
      />,
    )

    for (const key of ['name', 'type.label', 'parent', 'manager', 'users', 'companies', 'operationalSites']) {
      expect(pencilOf(label(key))).not.toBeInTheDocument()
    }
    await screen.findByText('Existing note')
    expect(pencilOf('Notes')).not.toBeInTheDocument()
  })
})
