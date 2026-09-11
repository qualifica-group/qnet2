import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { CompanyForm } from '@/features/companies/company-form'
import type { CompanyDetailWithPermissions } from '@/features/companies/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'

/**
 * Spec 0021 AC-026: the Companies module is the zero-code pilot for the
 * generic custom-fields renderer — mounting `<CustomFieldsSection>` is the
 * ONLY companies-specific integration. This suite exercises the wiring
 * (schema, defaults, payload, 422) without touching the section's own
 * per-type rendering (covered by `CustomFieldsSection.test.tsx`).
 */

const createCompanyMock = vi.fn()
const updateCompanyMock = vi.fn()

vi.mock('@/features/companies/api', () => ({
  createCompany: (...args: unknown[]) => createCompanyMock(...args),
  updateCompany: (...args: unknown[]) => updateCompanyMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

// Same reason for the national default of the cascade (public config + country
// list): stubbed to international mode, covered by `use-default-country.test.ts`.
vi.mock('@/features/geo/use-default-country', () => ({
  useDefaultCountryId: () => null,
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

function company(
  overrides: Partial<CompanyDetailWithPermissions> = {},
): CompanyDetailWithPermissions {
  return {
    id: 7,
    denomination: 'Acme Srl',
    vat_number: 'IT12345678903',
    address: null,
    created_at: null,
    permissions: permissionsWithNotes(),
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createCompanyMock.mockReset()
  updateCompanyMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [NOTES_FIELD], permissions: permissionsWithNotes() })
})

describe('CompanyForm — custom fields pilot (spec 0021 AC-026)', () => {
  it('renders the resource custom field control in create mode', async () => {
    render(
      <CompanyForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('textbox', { name: 'Notes' })).toBeInTheDocument()
  })

  it('includes the valued custom field in the create payload', async () => {
    createCompanyMock.mockResolvedValue(company())
    const onSuccess = vi.fn()

    render(
      <CompanyForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(await screen.findByLabelText(/^Denomination/), { target: { value: 'Acme Srl' } })
    fireEvent.change(await screen.findByRole('textbox', { name: 'Notes' }), {
      target: { value: 'Key account' },
    })
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createCompanyMock).toHaveBeenCalledTimes(1))
    const payload = createCompanyMock.mock.calls[0][0]
    expect(payload.custom_fields).toEqual({ notes: 'Key account' })
  })

  it('seeds the custom field value from the loaded company detail in edit mode', async () => {
    render(
      <CompanyForm
        mode={{ type: 'edit', company: company({ custom_fields: { notes: 'Existing note' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('textbox', { name: 'Notes' })).toHaveValue('Existing note')
  })

  it('sends only the changed custom field on a partial update', async () => {
    const original = company({ custom_fields: { notes: 'Existing note' } })
    updateCompanyMock.mockResolvedValue(original)

    render(
      <CompanyForm mode={{ type: 'edit', company: original }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    const notes = await screen.findByRole('textbox', { name: 'Notes' })
    fireEvent.change(notes, { target: { value: 'Updated note' } })
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateCompanyMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateCompanyMock.mock.calls[0]
    expect(payload).toEqual({ custom_fields: { notes: 'Updated note' } })
  })

  it('maps a 422 on custom_fields.<key> inline on the matching control', async () => {
    updateCompanyMock.mockRejectedValue(
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
            errors: { 'custom_fields.notes': ['Notes must be shorter.'] },
          },
        } as never,
      ),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <CompanyForm
        mode={{ type: 'edit', company: company({ custom_fields: { notes: 'Existing note' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await screen.findByRole('textbox', { name: 'Notes' })
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('Notes must be shorter.')).toBeInTheDocument())
    expect(updateCompanyMock).toHaveBeenCalledTimes(1)

    vi.restoreAllMocks()
  })
})
