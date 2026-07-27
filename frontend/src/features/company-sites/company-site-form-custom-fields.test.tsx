import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { CompanySiteForm } from '@/features/company-sites/company-site-form'
import type { CompanySiteDetailWithPermissions } from '@/features/company-sites/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'

/**
 * Spec 0021: the generic custom-fields renderer wired into the Company Sites
 * module — mounting `<CustomFieldsSection>` in the Profilo tab is the ONLY
 * company-sites-specific integration. Mirrors
 * `company-form-custom-fields.test.tsx` (the pilot module); per-type control
 * rendering is covered by `CustomFieldsSection.test.tsx`.
 */

const createCompanySiteMock = vi.fn()
const updateCompanySiteMock = vi.fn()

vi.mock('@/features/company-sites/api', () => ({
  createCompanySite: (...args: unknown[]) => createCompanySiteMock(...args),
  updateCompanySite: (...args: unknown[]) => updateCompanySiteMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/features/geo/use-geo', () => ({
  useCountries: () => ({ data: [{ id: 1, name: 'Italy', iso2: 'IT' }], isPending: false, isError: false }),
  useStates: () => ({ data: [], isPending: false, isError: false }),
  useProvinces: () => ({ data: [], isPending: false, isError: false }),
  useCities: () => ({
    data: { pages: [[]] },
    isPending: false,
    isError: false,
    hasNextPage: false,
    isFetchingNextPage: false,
    fetchNextPage: () => {},
    refetch: () => {},
  }),
}))

vi.mock('@/features/for-select/use-for-select', () => ({
  useForSelect: () => ({
    data: undefined,
    isPending: false,
    isError: false,
    fetchNextPage: () => {},
    hasNextPage: false,
    isFetchingNextPage: false,
    refetch: () => {},
  }),
  flattenForSelectPages: () => [],
  useForSelectLabels: () => new Map(),
}))

vi.mock('@/features/config/use-config', () => ({
  useConfig: () => ({ data: { enums: {} } }),
  useEnumOptions: () => [],
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
  key: 'custom.priority',
  type: 'text',
  label: 'Priority',
  group: null,
  mandatory: false,
  source: 'custom',
}

function permissionsWithPriority(): ResourcePermissions {
  return {
    resource: FULL_ACCESS,
    fields: {
      'custom.priority': {
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

function companySite(
  overrides: Partial<CompanySiteDetailWithPermissions> = {},
): CompanySiteDetailWithPermissions {
  return {
    id: 7,
    name: 'Sede Nord',
    notes: null,
    is_default: false,
    logo_url: null,
    personal_data: {
      id: 99,
      type: 'company',
      first_name: null,
      last_name: null,
      company_name: 'ACME S.p.A.',
      full_name: 'ACME S.p.A.',
      ceo: null,
      tax_code: null,
      vat_number: null,
      sdi_code: null,
      birth_date: null,
      birth_city_id: null,
      gender: null,
      personable_type: 'company_site',
      personable_id: 7,
      contacts: [],
      addresses: [],
      created_at: null,
    },
    banks: [],
    company: null,
    created_at: null,
    permissions: permissionsWithPriority(),
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createCompanySiteMock.mockReset()
  updateCompanySiteMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [PRIORITY_FIELD], permissions: permissionsWithPriority() })
})

describe('CompanySiteForm — custom fields (spec 0021)', () => {
  it('renders the resource custom field control in the Profilo tab', async () => {
    render(
      <CompanySiteForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('textbox', { name: 'Priority' })).toBeInTheDocument()
  })

  it('includes the valued custom field in the create payload', async () => {
    createCompanySiteMock.mockResolvedValue(companySite())
    const onSuccess = vi.fn()

    render(
      <CompanySiteForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(await screen.findByLabelText(/^Name/), { target: { value: 'Sede Nord' } })
    fireEvent.change(await screen.findByLabelText(/^Company name/), {
      target: { value: 'ACME S.p.A.' },
    })
    fireEvent.change(await screen.findByRole('textbox', { name: 'Priority' }), {
      target: { value: 'High priority' },
    })

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createCompanySiteMock).toHaveBeenCalledTimes(1))
    const payload = createCompanySiteMock.mock.calls[0][0]
    expect(payload.custom_fields).toEqual({ priority: 'High priority' })
  })

  it('seeds the custom field value from the loaded site detail in edit mode', async () => {
    render(
      <CompanySiteForm
        mode={{ type: 'edit', companySite: companySite({ custom_fields: { priority: 'Existing priority' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )
    expect(await screen.findByRole('textbox', { name: 'Priority' })).toHaveValue('Existing priority')
  })

  it('sends only the changed custom field on a partial update', async () => {
    const original = companySite({ custom_fields: { priority: 'Existing priority' } })
    updateCompanySiteMock.mockResolvedValue(original)

    render(
      <CompanySiteForm mode={{ type: 'edit', companySite: original }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )
    const notes = await screen.findByRole('textbox', { name: 'Priority' })
    fireEvent.change(notes, { target: { value: 'Updated priority' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateCompanySiteMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateCompanySiteMock.mock.calls[0]
    expect(payload).toEqual({ custom_fields: { priority: 'Updated priority' } })
  })

  it('maps a 422 on custom_fields.<key> inline on the matching control', async () => {
    updateCompanySiteMock.mockRejectedValue(
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
            errors: { 'custom_fields.priority': ['Priority must be shorter.'] },
          },
        } as never,
      ),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <CompanySiteForm
        mode={{ type: 'edit', companySite: companySite({ custom_fields: { priority: 'Existing priority' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )
    await screen.findByRole('textbox', { name: 'Priority' })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('Priority must be shorter.')).toBeInTheDocument())
    expect(updateCompanySiteMock).toHaveBeenCalledTimes(1)

    vi.restoreAllMocks()
  })
})
