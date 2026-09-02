import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { LeadForm } from '@/features/leads/lead-form'
import type { LeadDetailWithPermissions } from '@/features/leads/types'
import type { ResourceMeta } from '@/features/authorization/types'
import type { ForSelectItem } from '@/features/for-select/types'

/**
 * Spec 0094, AC-040/AC-041/AC-042/AC-043: the Lead form's "Prodotti di
 * interesse" section end to end (hook wiring covered in depth by
 * `use-lead-campaign-product-interest.test.tsx`). Split out of
 * `lead-form-body.test.tsx` for size (engineering.md §6).
 */

const createLeadMock = vi.fn()
const updateLeadMock = vi.fn()

vi.mock('@/features/leads/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/leads/api')>('@/features/leads/api')
  return {
    ...actual,
    createLead: (...args: unknown[]) => createLeadMock(...args),
    updateLead: (...args: unknown[]) => updateLeadMock(...args),
  }
})

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const FULL_PERMISSIONS = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

const CAMPAIGN_A = {
  id: 20,
  label: 'Spring push',
  meta: { product_category_ids: [10, 20] },
} as ForSelectItem
const CAMPAIGN_B = {
  id: 30,
  label: 'Autumn push',
  meta: { product_category_ids: [99] },
} as ForSelectItem

/**
 * Stubs every single-select field, keyed by its accessible trigger label. The
 * Campaign button toggles between two campaigns (each with a distinct
 * `meta.product_category_ids`) so a test can exercise both AC-041 (scoping)
 * and AC-042 (an incompatible switch).
 */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    onItemChange,
    labels,
    disabled,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    onItemChange?: (item: ForSelectItem | null) => void
    labels: { triggerLabel: string }
    disabled?: boolean
  }) => (
    <button
      type="button"
      data-testid={`select-${labels.triggerLabel}`}
      disabled={disabled}
      onClick={() => {
        if (labels.triggerLabel === 'Campaign') {
          const next = value === CAMPAIGN_A.id ? CAMPAIGN_B : CAMPAIGN_A
          onChange(next.id)
          onItemChange?.(next)
          return
        }
        onChange(3)
        onItemChange?.(null)
      }}
    >
      {value ?? ''}
    </button>
  ),
}))

const PRODUCT_501 = { id: 501, label: 'Widget', meta: { category_id: 10 } }

/** Stubs the "Prodotti di interesse" picker: exposes `params`/`disabled` for assertions and one button to add product 501. */
vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({
    value,
    onChange,
    labels,
    disabled,
    params,
  }: {
    value: number[]
    onChange: (value: number[]) => void
    labels: { triggerLabel: string }
    disabled?: boolean
    params?: Record<string, unknown>
  }) => (
    <div
      data-testid={`multi-${labels.triggerLabel}`}
      data-disabled={disabled ? 'true' : 'false'}
      data-params={JSON.stringify(params ?? {})}
    >
      <span data-testid="multi-value">{value.join(',')}</span>
      <button type="button" onClick={() => onChange([...value, PRODUCT_501.id])}>
        add product
      </button>
    </div>
  ),
}))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>(
    '@/features/for-select/api',
  )
  return {
    ...actual,
    fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
  }
})

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

function lead(overrides: Partial<LeadDetailWithPermissions> = {}): LeadDetailWithPermissions {
  return {
    id: 9,
    registry_id: 10,
    registry: { id: 10, name: 'Mario Rossi' },
    campaign_id: 20,
    campaign: { id: 20, code: 'CMP-0001', name: 'Spring push' },
    lead_status: 'not_associated',
    operational_site_id: null,
    operational_site: null,
    source_id: null,
    source: null,
    operator_id: null,
    operator: null,
    notes: null,
    extra_fields: null,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: FULL_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createLeadMock.mockReset()
  updateLeadMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })
  canMock.mockReset()
  canMock.mockReturnValue(true)
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation((resource: string, params: { ids?: number[] }) =>
    Promise.resolve({
      items: resource === 'products' && params?.ids?.includes(PRODUCT_501.id) ? [PRODUCT_501] : [],
      pagination: { offset: 0, limit: 25, total: 0 },
      export_link: null,
    }),
  )
})

describe('LeadFormBody — Prodotti di interesse (spec 0094)', () => {
  it('AC-040: the section is disabled until a Campaign is chosen', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Campaign')).toBeInTheDocument())
    expect(screen.getByTestId('multi-Products of interest')).toHaveAttribute('data-disabled', 'true')

    fireEvent.click(screen.getByTestId('select-Campaign'))

    await waitFor(() =>
      expect(screen.getByTestId('multi-Products of interest')).toHaveAttribute('data-disabled', 'false'),
    )
  })

  it('AC-041: the picker is scoped to the chosen Campaign\'s effective categories', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Campaign')).toBeInTheDocument())
    fireEvent.click(screen.getByTestId('select-Campaign'))

    await waitFor(() =>
      expect(screen.getByTestId('multi-Products of interest')).toHaveAttribute(
        'data-params',
        JSON.stringify({ category_ids: [10, 20] }),
      ),
    )
  })

  it('AC-042: switching to a Campaign that no longer covers a chosen product asks for confirmation and blocks Submit', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Campaign')).toBeInTheDocument())
    fireEvent.click(screen.getByTestId('select-Registry'))
    // Campaign A (categories [10, 20]) — the products picker unlocks.
    fireEvent.click(screen.getByTestId('select-Campaign'))
    await waitFor(() =>
      expect(screen.getByTestId('multi-Products of interest')).toHaveAttribute('data-disabled', 'false'),
    )

    fireEvent.click(screen.getByRole('button', { name: 'add product' }))
    expect(screen.getByTestId('multi-value')).toHaveTextContent('501')

    // Let the guard's own label resolution settle before the switch, so the
    // incompatibility is actually detected (no artificial race): await the
    // EXACT resolved for-select response, not just a microtask tick — a busy
    // full-suite run needs more than one tick to flush the query state.
    await waitFor(() =>
      expect(fetchForSelectMock).toHaveBeenCalledWith('products', expect.objectContaining({ ids: [501] })),
    )
    const productsCallIndex = fetchForSelectMock.mock.calls.findIndex(
      ([resource, params]) =>
        resource === 'products' &&
        JSON.stringify((params as { ids?: number[] })?.ids) === JSON.stringify([501]),
    )
    await act(async () => {
      await fetchForSelectMock.mock.results[productsCallIndex].value
    })

    // Campaign B (category [99]) no longer covers product 501 (category 10).
    fireEvent.click(screen.getByTestId('select-Campaign'))

    const dialog = await screen.findByRole('alertdialog')
    expect(dialog).toHaveTextContent('Widget')

    // AC-042: "senza conferma il submit non parte" — the open confirm dialog
    // (Radix `aria-hidden`s the rest of the page while modal) already makes
    // Save unreachable via its accessible role; `hidden: true` bypasses that
    // filter here to additionally assert the explicit `disabled` gate.
    expect(screen.getByRole('button', { name: 'Save', hidden: true })).toBeDisabled()
    expect(createLeadMock).not.toHaveBeenCalled()

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    await waitFor(() => expect(screen.getByRole('button', { name: 'Save' })).not.toBeDisabled())
    // Cancelling keeps the previous campaign and the product untouched.
    expect(screen.getByTestId('select-Campaign')).toHaveTextContent(String(CAMPAIGN_A.id))
    expect(screen.getByTestId('multi-value')).toHaveTextContent('501')
  })

  it('AC-043: maps 422s on products_of_interest and campaign_id onto their fields', async () => {
    updateLeadMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: {
          success: false,
          message: 'Validation failed',
          errors: {
            products_of_interest: ['One of the chosen products is outside the campaign categories.'],
            campaign_id: ['The new campaign does not cover the products already saved on this lead.'],
          },
        },
      } as never),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <LeadForm mode={{ type: 'edit', lead: lead() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByTestId('select-Campaign')).toBeInTheDocument())
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(
        screen.getByText('One of the chosen products is outside the campaign categories.'),
      ).toBeInTheDocument(),
    )
    expect(
      screen.getByText('The new campaign does not cover the products already saved on this lead.'),
    ).toBeInTheDocument()
    expect(updateLeadMock).toHaveBeenCalledTimes(1)

    vi.restoreAllMocks()
  })
})
