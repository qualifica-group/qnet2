import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { OperationalSiteForm } from '@/features/operational-sites/operational-site-form'
import type { OperationalSiteDetailWithPermissions } from '@/features/operational-sites/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * Acceptance criterion AC-018 (spec 0011): the metadata-driven behaviour of
 * the form (hidden field absent, readonly field not editable, required field
 * marked, server 422 mapped inline, geo cascade visibility/edit aggregation).
 * The operational-site CRUD behaviour itself (payload shaping, hydration,
 * cascade wiring) is covered by `operational-site-form.test.tsx`.
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

// GeoSelect is covered by its own test; here we only need a controllable stub
// that exposes its `disabled` prop and presence, mirroring
// `address-form.test.tsx`.
vi.mock('@/features/geo/geo-select', () => ({
  GeoSelect: ({ disabled }: { disabled?: boolean }) => (
    <div data-testid="geo-select" data-disabled={disabled ? 'true' : 'false'} />
  ),
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
  createOperationalSiteMock.mockReset()
  updateOperationalSiteMock.mockReset()
  fetchResourceMetaMock.mockReset()
})

describe('OperationalSiteForm — metadata-driven authorization (spec 0004)', () => {
  it('hides a hidden field and marks a required field from create-context metadata', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
        fields: {
          line1: {
            visible: true, hidden: false, editable: true, readonly: false, required: true, disabled: false,
          },
          postal_code: {
            visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: false,
          },
          country_id: {
            visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false,
          },
          state_id: {
            visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false,
          },
          province_id: {
            visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false,
          },
          city_id: {
            visible: true, hidden: false, editable: true, readonly: false, required: true, disabled: false,
          },
        },
        actions: {},
      },
    })

    render(
      <OperationalSiteForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    // The hidden field is absent from the DOM.
    await waitFor(() => expect(screen.getByLabelText(/^Street/)).toBeInTheDocument())
    expect(screen.queryByLabelText(/^Postal code/)).not.toBeInTheDocument()

    // `required` from metadata drives the label's `*`.
    expect(labelFor('Street').textContent).toContain('*')

    // The geo cascade is visible (at least one of the four levels is).
    expect(screen.getByTestId('geo-select')).toBeInTheDocument()
  })

  it('renders a readonly/non-editable field disabled in edit mode', () => {
    render(
      <OperationalSiteForm
        mode={{
          type: 'edit',
          operationalSite: operationalSite({
            permissions: {
              resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
              fields: {
                line1: {
                  visible: true, hidden: false, editable: false, readonly: true, required: false, disabled: false,
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

    const line1 = screen.getByLabelText(/^Street/)
    expect(line1).toBeDisabled()
    expect(line1).toHaveAttribute('readonly')
  })

  it('falls back to visible+editable when a field is missing from metadata', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
        fields: {
          // `postal_code` intentionally absent from the metadata.
          line1: {
            visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false,
          },
        },
        actions: {},
      },
    })

    render(
      <OperationalSiteForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByLabelText(/^Street/)).toBeInTheDocument())
    // No crash, and the postal code field renders (the graceful default).
    expect(screen.getByLabelText(/^Postal code/)).toBeInTheDocument()
  })

  it('locks the geo cascade as a whole when any of its levels is not editable', () => {
    render(
      <OperationalSiteForm
        mode={{
          type: 'edit',
          operationalSite: operationalSite({
            permissions: {
              resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
              fields: {
                city_id: {
                  visible: true, hidden: false, editable: false, readonly: true, required: true, disabled: false,
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

    expect(screen.getByTestId('geo-select')).toHaveAttribute('data-disabled', 'true')
  })

  it('does not render the geo cascade when every level is hidden', () => {
    render(
      <OperationalSiteForm
        mode={{
          type: 'edit',
          operationalSite: operationalSite({
            permissions: {
              resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
              fields: {
                country_id: {
                  visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: false,
                },
                state_id: {
                  visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: false,
                },
                province_id: {
                  visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: false,
                },
                city_id: {
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

    expect(screen.queryByTestId('geo-select')).not.toBeInTheDocument()
  })

  it('seeds permissions from the loaded detail and surfaces a 422 field error inline', async () => {
    updateOperationalSiteMock.mockRejectedValue(
      new AxiosError(
        'Unprocessable',
        '422',
        undefined,
        undefined,
        {
          status: 422,
          data: { success: false, message: 'Validation failed', errors: { line1: ['field not editable'] } },
        } as never,
      ),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <OperationalSiteForm
        mode={{ type: 'edit', operationalSite: operationalSite() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('field not editable')).toBeInTheDocument())
    expect(updateOperationalSiteMock).toHaveBeenCalledTimes(1)

    vi.restoreAllMocks()
  })
})
