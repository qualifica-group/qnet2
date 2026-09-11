import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { OperationalSiteForm } from '@/features/operational-sites/operational-site-form'
import type { OperationalSiteDetailWithPermissions } from '@/features/operational-sites/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'
import type { GeoValue } from '@/features/geo/geo-select'

/**
 * Spec 0021: the generic custom-fields renderer wired into the Operational
 * Sites module — mounting `<CustomFieldsSection>` is the ONLY
 * operational-sites-specific integration. Mirrors
 * `company-form-custom-fields.test.tsx` (the pilot module); per-type control
 * rendering is covered by `CustomFieldsSection.test.tsx`.
 */

const createOperationalSiteMock = vi.fn()
const updateOperationalSiteMock = vi.fn()

vi.mock('@/features/operational-sites/api', () => ({
  createOperationalSite: (...args: unknown[]) => createOperationalSiteMock(...args),
  updateOperationalSite: (...args: unknown[]) => updateOperationalSiteMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

// GeoSelect is covered by its own test; a controllable stub lets us pick a
// city in one click (`city_id` is the only mandatory geo level, spec 0011).
vi.mock('@/features/geo/geo-select', () => ({
  GeoSelect: ({ onChange, disabled }: { value: GeoValue; onChange: (next: GeoValue) => void; disabled?: boolean }) => (
    <button
      type="button"
      data-testid="geo-select"
      disabled={disabled}
      onClick={() => onChange({ country_id: 1, state_id: 2, province_id: 3, city_id: 4 })}
    >
      geo
    </button>
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

const ZONE_FIELD: CustomFieldDescriptor = {
  key: 'custom.zone',
  type: 'text',
  label: 'Zone',
  group: null,
  mandatory: false,
  source: 'custom',
}

function permissionsWithZone(): ResourcePermissions {
  return {
    resource: FULL_ACCESS,
    fields: {
      'custom.zone': {
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

function operationalSite(
  overrides: Partial<OperationalSiteDetailWithPermissions> = {},
): OperationalSiteDetailWithPermissions {
  return {
    id: 9,
    alias: null,
    line1: 'Via Roma 1',
    postal_code: '20100',
    country_id: 1,
    country: { id: 1, name: 'Italy' },
    state_id: 2,
    region: { id: 2, name: 'Lombardy' },
    province_id: 3,
    province: { id: 3, name: 'Milan' },
    city_id: 4,
    city: { id: 4, name: 'Milan' },
    created_at: '2026-01-01T00:00:00Z',
    permissions: permissionsWithZone(),
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createOperationalSiteMock.mockReset()
  updateOperationalSiteMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [ZONE_FIELD], permissions: permissionsWithZone() })
})

describe('OperationalSiteForm — custom fields (spec 0021)', () => {
  it('renders the resource custom field control in create mode', async () => {
    render(
      <OperationalSiteForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('textbox', { name: 'Zone' })).toBeInTheDocument()
  })

  it('includes the valued custom field in the create payload', async () => {
    createOperationalSiteMock.mockResolvedValue(operationalSite())
    const onSuccess = vi.fn()

    render(
      <OperationalSiteForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(await screen.findByLabelText(/^Street/), { target: { value: 'Via Torino 3' } })
    fireEvent.click(screen.getByTestId('geo-select'))
    fireEvent.change(await screen.findByRole('textbox', { name: 'Zone' }), {
      target: { value: 'North' },
    })

    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createOperationalSiteMock).toHaveBeenCalledTimes(1))
    const payload = createOperationalSiteMock.mock.calls[0][0]
    expect(payload.custom_fields).toEqual({ zone: 'North' })
  })

  it('seeds the custom field value from the loaded site detail in edit mode', async () => {
    render(
      <OperationalSiteForm
        mode={{ type: 'edit', operationalSite: operationalSite({ custom_fields: { zone: 'South' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('textbox', { name: 'Zone' })).toHaveValue('South')
  })

  it('sends only the changed custom field on a partial update', async () => {
    const original = operationalSite({ custom_fields: { zone: 'South' } })
    updateOperationalSiteMock.mockResolvedValue(original)

    render(
      <OperationalSiteForm mode={{ type: 'edit', operationalSite: original }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    const zone = await screen.findByRole('textbox', { name: 'Zone' })
    fireEvent.change(zone, { target: { value: 'North-East' } })
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateOperationalSiteMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateOperationalSiteMock.mock.calls[0]
    expect(payload).toEqual({ custom_fields: { zone: 'North-East' } })
  })

  it('maps a 422 on custom_fields.<key> inline on the matching control', async () => {
    updateOperationalSiteMock.mockRejectedValue(
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
            errors: { 'custom_fields.zone': ['Zone must be shorter.'] },
          },
        } as never,
      ),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <OperationalSiteForm
        mode={{ type: 'edit', operationalSite: operationalSite({ custom_fields: { zone: 'South' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await screen.findByRole('textbox', { name: 'Zone' })
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('Zone must be shorter.')).toBeInTheDocument())
    expect(updateOperationalSiteMock).toHaveBeenCalledTimes(1)

    vi.restoreAllMocks()
  })
})
