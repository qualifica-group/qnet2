import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { CompanyForm } from '@/features/companies/company-form'
import type { CompanyDetailWithPermissions } from '@/features/companies/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * Spec 0004 (AC-014/AC-015/AC-016): the metadata-driven behaviour of the form
 * (hidden field absent, readonly field disabled, required field marked, the
 * whole address section gated as one block, edit-seeded permissions + 422
 * mapping, graceful fallback). The companies-CRUD behaviour itself (payload
 * shaping, hydration, …) is covered by `company-form.test.tsx`.
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

/**
 * The `<label>` element whose text is exactly `text`, ignoring the trailing
 * `required` marker. Exact (not prefix) match: `line1`/`line2` share the
 * "Address" prefix, so a `startsWith` helper would be ambiguous here.
 */
function labelFor(text: string): HTMLElement {
  return screen.getByText(
    (_, element) =>
      element?.tagName === 'LABEL' && element.textContent?.replace('*', '').trim() === text,
  )
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
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
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
})

describe('CompanyForm — metadata-driven authorization (spec 0004)', () => {
  it('hides a hidden field, disables a readonly field, marks a required field', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
        fields: {
          denomination: {
            visible: true, hidden: false, editable: true, readonly: false, required: true, disabled: false,
          },
          vat_number: {
            visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: false,
          },
          address: {
            visible: true, hidden: false, editable: false, readonly: true, required: false, disabled: false,
          },
        },
        actions: {},
      },
    })

    render(
      <CompanyForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    // The hidden field is absent from the DOM.
    await waitFor(() => expect(screen.getByLabelText(/^Denomination/)).toBeInTheDocument())
    expect(screen.queryByLabelText(/^VAT number/)).not.toBeInTheDocument()

    // The non-editable address section renders its inputs disabled.
    expect(screen.getByLabelText(/^Address$/)).toBeDisabled()

    // `required` from metadata drives the label's `*` — denomination is
    // required, the (visible but readonly) address is not.
    expect(labelFor('Denomination').textContent).toContain('*')
    expect(labelFor('Address').textContent).not.toContain('*')
  })

  it('hides the whole address section when its field permission is hidden', () => {
    render(
      <CompanyForm
        mode={{
          type: 'edit',
          company: company({
            permissions: {
              resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
              fields: {
                address: {
                  visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: false,
                },
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

    expect(screen.queryByLabelText(/^Address$/)).not.toBeInTheDocument()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
  })

  it('falls back to visible+editable when a field is missing from metadata', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
        fields: {
          // `vat_number` intentionally absent from the metadata.
          denomination: {
            visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false,
          },
        },
        actions: {},
      },
    })

    render(
      <CompanyForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByLabelText(/^Denomination/)).toBeInTheDocument())
    // No crash, and the field renders visible + editable (the graceful default).
    expect(screen.getByLabelText(/^VAT number/)).toBeEnabled()
  })

  it('seeds permissions from the loaded detail and surfaces a 422 field error inline', async () => {
    updateCompanyMock.mockRejectedValue(
      new AxiosError(
        'Unprocessable',
        '422',
        undefined,
        undefined,
        {
          status: 422,
          data: { success: false, message: 'Validation failed', errors: { denomination: ['field not editable'] } },
        } as never,
      ),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <CompanyForm mode={{ type: 'edit', company: company() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('field not editable')).toBeInTheDocument())
    expect(updateCompanyMock).toHaveBeenCalledTimes(1)

    vi.restoreAllMocks()
  })
})
