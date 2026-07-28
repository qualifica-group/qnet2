import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RegistryForm } from '@/features/registries/registry-form'
import type { RegistryDetailWithPermissions } from '@/features/registries/types'
import type { ResourceMeta } from '@/features/authorization/types'
import type { EnumOption } from '@/features/config/types'
import type { PersonalDataCard } from '@/features/personal-data/types'

/**
 * Acceptance criteria AC-020/AC-021/AC-022 (spec 0020): the metadata-driven
 * behaviour of the registry form (hidden field absent, readonly field not
 * editable, required field marked, server 422 mapped inline) plus the
 * `is_qualified_supplier` conditional visibility (`form.watch('is_supplier')`).
 * The payload-shaping behaviour itself is covered by
 * `registry-form-payload.test.ts`.
 */

const createRegistryMock = vi.fn()
const updateRegistryMock = vi.fn()

vi.mock('@/features/registries/api', () => ({
  createRegistry: (...args: unknown[]) => createRegistryMock(...args),
  updateRegistry: (...args: unknown[]) => updateRegistryMock(...args),
  registryDetailQueryKey: (id: number | null) => ['registries', 'detail', id] as const,
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const enums: Record<string, EnumOption[]> = {
  personal_data_type: [
    { value: 'individual', label: 'Individual', color: null, icon: null, is_default: true, hidden_on_form: false },
    { value: 'company', label: 'Company', color: null, icon: null, is_default: false, hidden_on_form: false },
  ],
  contact_type: [],
  agreement_status: [
    { value: 'negotiating', label: 'Negotiating', color: null, icon: null, is_default: true, hidden_on_form: false },
    { value: 'rejected', label: 'Rejected', color: null, icon: null, is_default: false, hidden_on_form: false },
    { value: 'agreed', label: 'Agreed', color: null, icon: null, is_default: false, hidden_on_form: false },
  ],
  size_class: [
    { value: 'micro', label: 'Micro', color: null, icon: null, is_default: false, hidden_on_form: false },
    { value: 'small', label: 'Small', color: null, icon: null, is_default: false, hidden_on_form: false },
  ],
}

vi.mock('@/features/config/use-config', () => ({
  useConfig: () => ({ data: { enums } }),
  useEnumOptions: (key: string) => enums[key] ?? [],
}))

/** Stubs every single-select field, keyed by its accessible trigger label. */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    labels,
  }: {
    value: number | null
    labels: { triggerLabel: string }
  }) => <div data-testid={`select-${labels.triggerLabel}`}>{value ?? ''}</div>,
}))

/** Stubs every multiselect field, keyed by its accessible trigger label. */
vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({
    value,
    labels,
  }: {
    value: number[]
    labels: { triggerLabel: string }
  }) => <div data-testid={`multiselect-${labels.triggerLabel}`}>{value.join(',')}</div>,
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
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
    ...overrides,
  }
}

/** Full field-permission map (visible/editable) for every registry-specific field. */
const ALL_VISIBLE_EDITABLE = Object.fromEntries(
  [
    'source_id', 'sector_ids', 'referent_ids', 'manager_ids',
    'supervisor_id', 'commercial_id', 'reporter_id', 'vat_group',
    'is_supplier', 'is_qualified_supplier', 'agreement_status',
    'agreement_notes', 'size_class', 'employee_count',
  ].map((key) => [
    key,
    { visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false },
  ]),
)

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createRegistryMock.mockReset()
  updateRegistryMock.mockReset()
  fetchResourceMetaMock.mockReset()
})

describe('RegistryForm — metadata-driven authorization (spec 0004)', () => {
  it('hides a hidden field and marks a required field from create-context metadata', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
        fields: {
          ...ALL_VISIBLE_EDITABLE,
          source_id: { visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: false },
          vat_group: { visible: true, hidden: false, editable: true, readonly: false, required: true, disabled: false },
        },
        actions: {},
      },
    })

    render(
      <RegistryForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByLabelText(/^VAT group/)).toBeInTheDocument())
    expect(screen.queryByTestId('select-Source')).not.toBeInTheDocument()
    expect(labelFor('VAT group').textContent).toContain('*')
  })

  it('renders a readonly/non-editable field disabled in edit mode', () => {
    render(
      <RegistryForm
        mode={{
          type: 'edit',
          registry: registry({
            permissions: {
              resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
              fields: {
                ...ALL_VISIBLE_EDITABLE,
                vat_group: { visible: true, hidden: false, editable: false, readonly: true, required: false, disabled: false },
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

    const vatGroup = screen.getByLabelText(/^VAT group/)
    expect(vatGroup).toBeDisabled()
    expect(vatGroup).toHaveAttribute('readonly')
  })

  it('seeds permissions from the loaded detail and surfaces a 422 field error inline', async () => {
    updateRegistryMock.mockRejectedValue(
      new AxiosError(
        'Unprocessable',
        '422',
        undefined,
        undefined,
        {
          status: 422,
          data: { success: false, message: 'Validation failed', errors: { vat_group: ['field not editable'] } },
        } as never,
      ),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <RegistryForm mode={{ type: 'edit', registry: registry({ permissions: { resource: { view: true, create: true, update: true, delete: true, export: true, import: true }, fields: ALL_VISIBLE_EDITABLE, actions: {} } }) }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('field not editable')).toBeInTheDocument())
    expect(updateRegistryMock).toHaveBeenCalledTimes(1)

    vi.restoreAllMocks()
  })

  it('seeds the detail cache with the permissions envelope after a successful edit', async () => {
    // `updateRegistry` resolves with a bare RegistryDetail (no `permissions`),
    // exactly like the real API client. The cache the detail page reads must
    // still carry `permissions`, or its `registry.permissions.resource` access
    // crashes on the next render.
    const { permissions: _omit, ...bareSaved } = registry({ name: 'Renamed S.p.A.' })
    updateRegistryMock.mockResolvedValue(bareSaved)

    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    render(
      <QueryClientProvider client={client}>
        <RegistryForm
          mode={{
            type: 'edit',
            registry: registry({
              permissions: {
                resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
                fields: ALL_VISIBLE_EDITABLE,
                actions: {},
              },
            }),
          }}
          onSuccess={vi.fn()}
          onCancel={vi.fn()}
        />
      </QueryClientProvider>,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRegistryMock).toHaveBeenCalledTimes(1))

    const cached = client.getQueryData<RegistryDetailWithPermissions>(['registries', 'detail', 7])
    expect(cached?.permissions.resource.update).toBe(true)
    expect(cached?.name).toBe('Renamed S.p.A.')
  })
})

describe('RegistryForm — is_qualified_supplier conditional visibility (AC-021)', () => {
  const fullPermissions = {
    resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
    fields: ALL_VISIBLE_EDITABLE,
    actions: {},
  }

  it('hides the qualified-supplier toggle while is_supplier is off', () => {
    render(
      <RegistryForm
        mode={{ type: 'edit', registry: registry({ permissions: fullPermissions, is_supplier: false }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.queryByLabelText('Qualified supplier')).not.toBeInTheDocument()
  })

  it('shows the qualified-supplier toggle once is_supplier is on', () => {
    render(
      <RegistryForm
        mode={{ type: 'edit', registry: registry({ permissions: fullPermissions, is_supplier: true }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText('Qualified supplier')).toBeInTheDocument()
  })

  it('reveals/hides the toggle live when the supplier switch is flipped', () => {
    render(
      <RegistryForm
        mode={{ type: 'edit', registry: registry({ permissions: fullPermissions, is_supplier: false }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.queryByLabelText('Qualified supplier')).not.toBeInTheDocument()

    fireEvent.click(screen.getByLabelText('Supplier'))
    expect(screen.getByLabelText('Qualified supplier')).toBeInTheDocument()

    fireEvent.click(screen.getByLabelText('Supplier'))
    expect(screen.queryByLabelText('Qualified supplier')).not.toBeInTheDocument()
  })
})
