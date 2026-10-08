import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RegistryForm } from '@/features/registries/registry-form'
import type { RegistryDetailWithPermissions } from '@/features/registries/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'
import type { PersonalDataCard } from '@/features/personal-data/types'
import { fillCardNames } from '@/features/registries/registry-test-fixtures'

/**
 * Spec 0021: the generic custom-fields renderer wired into the Registries
 * module — mounting `<CustomFieldsSection>` in the form is the ONLY
 * registries-specific integration. Mirrors `company-form-custom-fields.test.tsx`
 * (the pilot module); per-type control rendering is covered by
 * `CustomFieldsSection.test.tsx`.
 */

const createRegistryMock = vi.fn()

vi.mock('@/features/registries/api', () => ({
  createRegistry: (...args: unknown[]) => createRegistryMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ value }: { value: number | null }) => (
    <div data-testid="registry-select-value">{value ?? ''}</div>
  ),
}))

vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({ value }: { value: number[] }) => (
    <div data-testid="registry-multi-select-value">{value.join(',')}</div>
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

const PRIORITY_FIELD: CustomFieldDescriptor = {
  key: 'custom.priority_level',
  type: 'text',
  label: 'Priority level',
  group: null,
  mandatory: false,
  source: 'custom',
}

function permissionsWithPriority(): ResourcePermissions {
  return {
    resource: FULL_ACCESS,
    fields: {
      'custom.priority_level': {
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
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

function card(overrides: Partial<PersonalDataCard> = {}): PersonalDataCard {
  return {
    id: 99,
    type: 'individual',
    first_name: 'Ada',
    last_name: 'Lovelace',
    company_name: null,
    full_name: 'Ada Lovelace',
    ceo: null,
    tax_code: null,
    vat_number: null,
    sdi_code: null,
    birth_date: null,
    birth_city_id: null,
    residence_city_id: null,
    gender: 'female',
    personable_type: 'registry',
    personable_id: 7,
    contacts: [],
    addresses: [],
    created_at: null,
    ...overrides,
  }
}

function registry(
  overrides: Partial<RegistryDetailWithPermissions> = {},
): RegistryDetailWithPermissions {
  return {
    id: 7,
    name: 'Ada Lovelace',
    source_id: null,
    source: null,
    sector_ids: [],
    sectors: [],
    referent_ids: [],
    referents: [],
    manager_ids: [],
    managers: [],
    manager_slots: [],
    supervisor_id: null,
    supervisor: null,
    commercial_id: null,
    commercial: null,
    reporter_id: null,
    reporter: null,
    vat_group: null,
    is_supplier: false,
    is_qualified_supplier: false,
    agreement_status: null,
    agreement_notes: null,
    size_class: null,
    employee_count: null,
    personal_data: card(),
    created_at: '2026-01-01T00:00:00Z',
    permissions: permissionsWithPriority(),
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createRegistryMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [PRIORITY_FIELD], permissions: permissionsWithPriority() })
})

describe('RegistryForm — custom fields (spec 0021)', () => {
  // REQUIREMENT CHANGED (spec 0200 D-5): the create rows start closed; the
  // pencil opens the field's control.
  it('renders the resource custom field as a closed row opening on its control', async () => {
    render(
      <RegistryForm onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Priority level' }))
    expect(screen.getByRole('textbox', { name: 'Priority level' })).toBeInTheDocument()
  })

  it('includes the valued custom field in the create payload', async () => {
    createRegistryMock.mockResolvedValue(registry())
    const onSuccess = vi.fn()

    render(
      <RegistryForm onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await fillCardNames()
    // Creating an anagrafica requires a phone number (user directive 2026-09-07).
    fireEvent.change(screen.getByLabelText(/^Phone/), { target: { value: '+39 333 1234567' } })
    fireEvent.click(await screen.findByRole('button', { name: 'Edit Priority level' }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Priority level' }), {
      target: { value: 'High' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Done' }))

    fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

    await waitFor(() => expect(createRegistryMock).toHaveBeenCalledTimes(1))
    const payload = createRegistryMock.mock.calls[0][0]
    expect(payload.custom_fields).toEqual({ priority_level: 'High' })
  })
  // REQUIREMENT CHANGED (spec 0200): the edit-mode cases (seed, sparse
  // PATCH, 422 inline) moved to `registry-detail-inline-edit.test.tsx`.
})
