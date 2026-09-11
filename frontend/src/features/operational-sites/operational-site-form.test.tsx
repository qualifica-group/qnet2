import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { OperationalSiteForm } from '@/features/operational-sites/operational-site-form'
import type { OperationalSiteDetailWithPermissions } from '@/features/operational-sites/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { GeoValue } from '@/features/geo/geo-select'

const createOperationalSiteMock = vi.fn()
const updateOperationalSiteMock = vi.fn()

vi.mock('@/features/operational-sites/api', () => ({
  createOperationalSite: (...args: unknown[]) => createOperationalSiteMock(...args),
  updateOperationalSite: (...args: unknown[]) => updateOperationalSiteMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/**
 * This suite is not about authorization metadata (covered by
 * `operational-site-form-metadata.test.tsx`): every field resolves as
 * visible+editable (the `MetaField` fallback, since `fields` is empty) so
 * create/edit render exactly as they would before spec 0004.
 */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/operational-sites/use-operational-site-form-meta', () => ({
  useOperationalSiteFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

// GeoSelect is covered by its own test (including the reset-on-parent-change
// cascade behaviour); here we only need a controllable stub that lets us
// observe the wired value and drive a full cascade selection in one click,
// mirroring `address-form.test.tsx` (AC-020: the form correctly forwards
// GeoSelect's already-reset value onto country_id/state_id/province_id/city_id).
vi.mock('@/features/geo/geo-select', () => ({
  GeoSelect: ({
    value,
    onChange,
    disabled,
  }: {
    value: GeoValue
    onChange: (next: GeoValue) => void
    disabled?: boolean
  }) => (
    <button
      type="button"
      data-testid="geo-select"
      data-country={value.country_id ?? ''}
      data-state={value.state_id ?? ''}
      data-province={value.province_id ?? ''}
      data-city={value.city_id ?? ''}
      disabled={disabled}
      onClick={() => onChange({ country_id: 5, state_id: 6, province_id: 8, city_id: 7 })}
    >
      geo
    </button>
  ),
}))

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
    alias: 'HQ',
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
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createOperationalSiteMock.mockReset()
  updateOperationalSiteMock.mockReset()
})

describe('OperationalSiteForm — create/edit', () => {
  it('AC-017 — renders the geo cascade, street and postal code fields in create mode', () => {
    render(
      <OperationalSiteForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByTestId('geo-select')).toBeInTheDocument()
    expect(screen.getByLabelText(/^Street/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Postal code/)).toBeInTheDocument()
  })

  it('submits the create payload on save', async () => {
    createOperationalSiteMock.mockResolvedValue(operationalSite())
    const onSuccess = vi.fn()

    render(
      <OperationalSiteForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Street/), { target: { value: 'Via Torino 3' } })
    fireEvent.change(screen.getByLabelText(/^Postal code/), { target: { value: '10100' } })
    fireEvent.click(screen.getByTestId('geo-select'))
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createOperationalSiteMock).toHaveBeenCalledTimes(1))
    expect(createOperationalSiteMock).toHaveBeenCalledWith({
      alias: null,
      line1: 'Via Torino 3',
      postal_code: '10100',
      country_id: 5,
      state_id: 6,
      province_id: 8,
      city_id: 7,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(operationalSite()))
  })

  it('hydrates the street, postal code and geo cascade in edit mode', () => {
    render(
      <OperationalSiteForm
        mode={{ type: 'edit', operationalSite: operationalSite() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Street/)).toHaveValue('Via Roma 1')
    expect(screen.getByLabelText(/^Postal code/)).toHaveValue('20100')

    const geo = screen.getByTestId('geo-select')
    expect(geo).toHaveAttribute('data-country', '1')
    expect(geo).toHaveAttribute('data-state', '2')
    expect(geo).toHaveAttribute('data-province', '3')
    expect(geo).toHaveAttribute('data-city', '4')
  })

  it('submits only the changed fields on a partial update', async () => {
    updateOperationalSiteMock.mockResolvedValue(operationalSite({ line1: 'Via Milano 9' }))

    render(
      <OperationalSiteForm
        mode={{ type: 'edit', operationalSite: operationalSite() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Street/), { target: { value: 'Via Milano 9' } })
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateOperationalSiteMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateOperationalSiteMock.mock.calls[0]
    expect(id).toBe(9)
    expect(payload).toEqual({ line1: 'Via Milano 9' })
  })

  it('AC-020 — forwards the cascade selection so city_id/province_id/state_id land coherently in the payload', async () => {
    createOperationalSiteMock.mockResolvedValue(operationalSite())

    render(
      <OperationalSiteForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Street/), { target: { value: 'Via Genova 4' } })
    fireEvent.click(screen.getByTestId('geo-select'))

    const geo = screen.getByTestId('geo-select')
    expect(geo).toHaveAttribute('data-state', '6')
    expect(geo).toHaveAttribute('data-province', '8')
    expect(geo).toHaveAttribute('data-city', '7')

    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createOperationalSiteMock).toHaveBeenCalledTimes(1))
    expect(createOperationalSiteMock).toHaveBeenCalledWith(
      expect.objectContaining({ state_id: 6, province_id: 8, city_id: 7 }),
    )
  })
})
