import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { BusinessFunctionForm } from '@/features/business-functions/business-function-form'
import type { BusinessFunctionDetailWithPermissions } from '@/features/business-functions/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'

/**
 * Spec 0237: the business function form is create-only, a replica of the
 * detail with its rows closed (the in-place edits live in
 * `business-function-detail.test.tsx`). Covers the relation rows (spec 0010),
 * the metadata-driven behaviour (spec 0004) and the custom fields (spec 0021).
 */

const createBusinessFunctionMock = vi.fn()

vi.mock('@/features/business-functions/api', () => ({
  createBusinessFunction: (...args: unknown[]) => createBusinessFunctionMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

// The closed relation rows name the picked ids through the for-select cache.
vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: (resource: string, params: { ids?: number[] }) =>
    Promise.resolve({
      items: (params.ids ?? []).map((id) => ({ id, label: `${resource} ${id}` })),
      export_link: null,
      pagination: { total: null, offset: 0, limit: 25, total_pages: null },
    }),
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

function permissions(fields: ResourcePermissions['fields'] = {}): ResourcePermissions {
  return {
    resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
    fields: { 'custom.notes': EDITABLE, ...fields },
    actions: {},
  }
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <MemoryRouter>{children}</MemoryRouter>
    </QueryClientProvider>
  )
}

function businessFunction(): BusinessFunctionDetailWithPermissions {
  return {
    id: 9,
    name: 'Support',
    is_business_unit: false,
    is_business_service: false,
    type: null,
    manager_id: 5,
    manager: null,
    user_ids: [],
    users: [],
    parent_id: null,
    parent: null,
    operational_site_ids: [],
    operational_sites: [],
    company_ids: [],
    companies: [],
    created_at: '2026-01-01T00:00:00Z',
    permissions: permissions(),
  }
}

const label = (key: string) => i18n.t(`businessFunctions.form.${key}`)

/** Opens a closed row through its pencil. */
function openRow(field: string) {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field }) }))
}

/** "Fatto": keeps the row's value in the draft and closes it. */
const done = () => fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.apply') }))

const save = () => fireEvent.click(screen.getAllByRole('button', { name: label('save') })[0])

/** `withNotes: false` drops the optional custom field: an untouched one is not yet submittable on a create. */
async function renderForm(onSuccess = vi.fn(), withNotes = true) {
  if (!withNotes) {
    fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissions() })
  }
  render(<BusinessFunctionForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })
  await screen.findAllByRole('button', { name: label('save') })
  if (withNotes) {
    await screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Notes' }) })
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createBusinessFunctionMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [NOTES_FIELD], permissions: permissions() })
})

describe('BusinessFunctionForm — create (spec 0237)', () => {
  it('starts with every row closed; each row opens on its own control', async () => {
    await renderForm()

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()

    openRow(label('name'))
    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    done()

    openRow(label('type.label'))
    expect(screen.getByRole('combobox', { name: 'Type' })).toBeInTheDocument()
    done()

    openRow(label('manager'))
    expect(screen.getByText('select-users-5')).toBeInTheDocument()
    done()

    openRow(label('parent'))
    expect(screen.getByText('select-business-functions-50')).toBeInTheDocument()
    done()

    openRow(label('users'))
    expect(screen.getByTestId('users-values')).toBeInTheDocument()
    done()

    openRow(label('operationalSites'))
    expect(screen.getByTestId('operational-sites-values')).toBeInTheDocument()
    done()

    openRow(label('companies'))
    expect(screen.getByTestId('companies-values')).toBeInTheDocument()
  })

  it('shows the error on the closed row and does not call the API when name is empty', async () => {
    await renderForm()

    save()

    expect(await screen.findByText(label('nameRequired'))).toHaveAttribute('role', 'alert')
    expect(createBusinessFunctionMock).not.toHaveBeenCalled()
  })

  it('names each picked relation on its closed row and submits the create payload', async () => {
    createBusinessFunctionMock.mockResolvedValue(businessFunction())
    const onSuccess = vi.fn()
    await renderForm(onSuccess, false)

    openRow(label('name'))
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Support' } })
    openRow(label('manager'))
    fireEvent.click(screen.getByText('select-users-5'))
    done()
    openRow(label('users'))
    fireEvent.click(screen.getByText('add-users-7'))
    done()
    openRow(label('parent'))
    fireEvent.click(screen.getByText('select-business-functions-50'))
    done()
    openRow(label('operationalSites'))
    fireEvent.click(screen.getByText('add-operational-sites-70'))
    done()
    openRow(label('companies'))
    fireEvent.click(screen.getByText('add-companies-70'))
    done()

    expect(await screen.findByText('business-functions 50')).toBeInTheDocument()
    expect(await screen.findByText('operational-sites 70')).toBeInTheDocument()
    expect(await screen.findByText('companies 70')).toBeInTheDocument()

    save()

    await waitFor(() => expect(createBusinessFunctionMock).toHaveBeenCalledTimes(1))
    expect(createBusinessFunctionMock).toHaveBeenCalledWith({
      name: 'Support',
      type: null,
      manager_id: 5,
      users: [7],
      parent_id: 50,
      operational_sites: [70],
      company_ids: [70],
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(businessFunction()))
  })

  it('maps a selected type option to its value', async () => {
    createBusinessFunctionMock.mockResolvedValue(businessFunction())
    await renderForm(vi.fn(), false)

    openRow(label('name'))
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Ops' } })
    openRow(label('type.label'))
    fireEvent.click(screen.getByRole('combobox', { name: 'Type' }))
    fireEvent.click(screen.getByRole('option', { name: 'Business Unit' }))
    save()

    await waitFor(() => expect(createBusinessFunctionMock).toHaveBeenCalledTimes(1))
    expect(createBusinessFunctionMock).toHaveBeenCalledWith(expect.objectContaining({ type: 'business_unit' }))
  })

  it('includes the valued custom field in the create payload', async () => {
    createBusinessFunctionMock.mockResolvedValue(businessFunction())
    await renderForm()

    openRow(label('name'))
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Ops' } })
    openRow('Notes')
    fireEvent.change(screen.getByRole('textbox', { name: 'Notes' }), { target: { value: 'A note' } })
    save()

    await waitFor(() => expect(createBusinessFunctionMock).toHaveBeenCalledTimes(1))
    expect(createBusinessFunctionMock.mock.calls[0][0].custom_fields).toEqual({ notes: 'A note' })
  })

  it('hides a hidden field from the create-context metadata', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: permissions({
        manager_id: { visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: false },
      }),
    })
    render(<BusinessFunctionForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })
    await screen.findAllByRole('button', { name: label('save') })

    expect(screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label('manager') }) })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label('users') }) })).toBeInTheDocument()
  })
})
