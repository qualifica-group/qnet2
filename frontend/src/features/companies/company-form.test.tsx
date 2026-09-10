import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { CompanyForm } from '@/features/companies/company-form'
import type { CompanyDetailWithPermissions } from '@/features/companies/types'
import type { ResourcePermissions } from '@/features/authorization/types'

const createCompanyMock = vi.fn()
const updateCompanyMock = vi.fn()

vi.mock('@/features/companies/api', () => ({
  createCompany: (...args: unknown[]) => createCompanyMock(...args),
  updateCompany: (...args: unknown[]) => updateCompanyMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/**
 * This suite is not about authorization metadata (covered by
 * `company-form-metadata.test.tsx`): every field resolves as visible+editable
 * (the `MetaField` fallback, since `fields` is empty) so create/edit render
 * exactly as they would before spec 0004.
 */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/companies/use-company-form-meta', () => ({
  useCompanyFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

// `useCompanyForm` reads `/meta/companies` (spec 0021) to build the dynamic
// custom-fields schema; this suite has no custom fields to exercise (covered
// by `company-form-custom-fields.test.tsx`), so it resolves to an empty catalogue.
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => Promise.resolve({ fields: [], permissions: FULL_ACCESS_PERMISSIONS }),
}))

// The cascading geo selects are network-backed (covered by their own
// component test); stub them so this suite focuses on the company form's own
// logic without touching `@/features/geo/api`.
// Same reason for the national default of the cascade (public config + country
// list): stubbed to international mode, covered by `use-default-country.test.ts`.
vi.mock('@/features/geo/use-default-country', () => ({
  useDefaultCountryId: () => null,
}))

vi.mock('@/features/geo/use-geo', () => ({
  useCountries: () => ({ data: [{ id: 1, name: 'Italy', iso2: 'IT' }], isPending: false, isError: false }),
  useStates: () => ({ data: [{ id: 10, name: 'Lombardy', country_id: 1 }], isPending: false, isError: false }),
  useProvinces: () => ({ data: [{ id: 50, name: 'Milan', state_id: 10 }], isPending: false, isError: false }),
  useCities: () => ({
    data: { pages: [[{ id: 100, name: 'Milan', state_id: 10, province_id: 50 }]] },
    isPending: false,
    isError: false,
    hasNextPage: false,
    isFetchingNextPage: false,
    fetchNextPage: () => {},
    refetch: () => {},
  }),
}))

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
    address: {
      id: 3,
      line1: '221B Baker Street',
      line2: null,
      postal_code: '20100',
      country_id: 1,
      state_id: 10,
      province_id: 50,
      city_id: 100,
      country: 'Italy',
      region: 'Lombardy',
      province: 'Milan',
      city: 'Milan',
      is_primary: true,
    },
    created_at: null,
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createCompanyMock.mockReset()
  updateCompanyMock.mockReset()
})

describe('CompanyForm — create/edit', () => {
  it('renders the denomination, VAT number and address fields in create mode', () => {
    render(
      <CompanyForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Denomination/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^VAT number/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Address$/)).toBeInTheDocument()
    // Comune-first layout: only the comune is a control until the disclosure
    // is opened; nazione/regione/provincia are derived from it and reachable
    // behind "Change geographic area".
    expect(screen.getAllByRole('combobox')).toHaveLength(1)

    fireEvent.click(screen.getByRole('button', { name: /Change geographic area/ }))
    expect(screen.getAllByRole('combobox')).toHaveLength(4)
  })

  it('submits one createCompany call carrying denomination, vat_number and address', async () => {
    createCompanyMock.mockResolvedValue(company())
    const onSuccess = vi.fn()

    render(
      <CompanyForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Denomination/), {
      target: { value: 'Acme Srl' },
    })
    fireEvent.change(screen.getByLabelText(/^VAT number/), {
      target: { value: 'IT12345678903' },
    })
    fireEvent.change(screen.getByLabelText(/^Address$/), {
      target: { value: '221B Baker Street' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createCompanyMock).toHaveBeenCalledTimes(1))
    expect(updateCompanyMock).not.toHaveBeenCalled()

    const payload = createCompanyMock.mock.calls[0][0]
    expect(payload.denomination).toBe('Acme Srl')
    expect(payload.vat_number).toBe('IT12345678903')
    expect(payload.address).toEqual({
      line1: '221B Baker Street',
      line2: null,
      postal_code: null,
      country_id: null,
      state_id: null,
      province_id: null,
      city_id: null,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalled())
  })

  it('omits the address entirely when it is left blank', async () => {
    createCompanyMock.mockResolvedValue(company({ address: null }))

    render(
      <CompanyForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Denomination/), {
      target: { value: 'Acme Srl' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createCompanyMock).toHaveBeenCalledTimes(1))
    const payload = createCompanyMock.mock.calls[0][0]
    expect('address' in payload).toBe(false)
  })

  it('blocks the save when the address is started but the required line1 is left blank', async () => {
    render(
      <CompanyForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Denomination/), {
      target: { value: 'Acme Srl' },
    })
    fireEvent.change(screen.getByLabelText(/^Postal code/), {
      target: { value: '20100' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(
        screen.getByText('Address is required when an address is provided.'),
      ).toBeInTheDocument(),
    )
    expect(createCompanyMock).not.toHaveBeenCalled()
  })

  it('hydrates the company and its address in edit mode', () => {
    render(
      <CompanyForm mode={{ type: 'edit', company: company() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Denomination/)).toHaveValue('Acme Srl')
    expect(screen.getByLabelText(/^VAT number/)).toHaveValue('IT12345678903')
    expect(screen.getByLabelText(/^Address$/)).toHaveValue('221B Baker Street')
    expect(screen.getByLabelText(/^Postal code/)).toHaveValue('20100')
  })

  it('submits only the changed fields on a partial update', async () => {
    updateCompanyMock.mockResolvedValue(company({ denomination: 'Acme Srl EU' }))

    render(
      <CompanyForm mode={{ type: 'edit', company: company() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Denomination/), {
      target: { value: 'Acme Srl EU' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateCompanyMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateCompanyMock.mock.calls[0]
    expect(id).toBe(7)
    expect(payload).toEqual({ denomination: 'Acme Srl EU' })
  })
})
