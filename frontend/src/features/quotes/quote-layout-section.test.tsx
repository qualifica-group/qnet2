import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { QuoteFormBody } from '@/features/quotes/quote-form-body'
import { createQuote } from '@/features/quotes/api'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { QuoteDetailWithPermissions } from '@/features/quotes/types'

/**
 * Spec 0070 AC-310/AC-312: the Layout field precompiles with the `quotes`
 * module's active default in create mode (without the user picking it), and
 * shows a persisted-but-deactivated layout's label in edit mode straight off
 * the loaded quote (the same `{id,name}` ref every other relation field
 * hydrates its `selected` prop from — no special-case code needed for a
 * deactivated layout). Renders the real `AsyncPaginatedSelect` (not
 * stubbed), only the HTTP layer is mocked — mirrors
 * `quote-form-opportunity-params.test.tsx`.
 */

vi.mock('@/features/quotes/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/quotes/api')>('@/features/quotes/api')
  return { ...actual, createQuote: vi.fn(), updateQuote: vi.fn(), fetchQuoteNextCode: vi.fn() }
})

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

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

const DEFAULT_LAYOUT_ITEM = {
  id: 12,
  label: 'Offerta economica',
  meta: { is_default: true, code: 'quote_default' },
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

function quoteFixture(overrides: Partial<QuoteDetailWithPermissions> = {}): QuoteDetailWithPermissions {
  return {
    id: 9,
    code: 'QUO-0009',
    title: 'Sample quote',
    opportunity_id: 55,
    opportunity: { id: 55, name: 'OPP_55' },
    quote_status_id: 1,
    quote_status: { id: 1, name: 'Bozza', color: 'slate', group: 'open' },
    commercial_id: null,
    commercial: null,
    reporter_id: null,
    reporter: null,
    supervisor_id: null,
    supervisor: null,
    company_id: null,
    company: null,
    company_site_id: null,
    company_site: null,
    operational_site_id: null,
    operational_site: null,
    layout_id: null,
    layout: null,
    internal_notes: null,
    offer_lines: [],
    cost_lines: [],
    summary: {
      revenue: { net: '0.00', vat: '0.00', gross: '0.00' },
      cost: { net: '0.00', vat: '0.00', gross: '0.00' },
      margin: { net: '0.00' },
    },
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue(EMPTY_PAGE)
})

describe('QuoteLayoutSection — create mode (AC-310)', () => {
  it('precompiles the field with the quotes module active default, without the user picking it', async () => {
    fetchForSelectMock.mockImplementation(
      (resource: string, params: { params?: Record<string, unknown> }) => {
        if (resource === 'document-layouts' && params.params?.module === 'quotes') {
          return Promise.resolve({
            items: [DEFAULT_LAYOUT_ITEM],
            pagination: { offset: 0, limit: 25, total: 1 },
            export_link: null,
          })
        }
        return Promise.resolve(EMPTY_PAGE)
      },
    )

    render(
      <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
        <QuoteFormBody mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} initialCode="" />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    await waitFor(() =>
      expect(screen.getByRole('combobox', { name: 'Layout' })).toHaveTextContent('Offerta economica'),
    )
  })

  it('carries the precompiled layout_id in the create payload', async () => {
    fetchForSelectMock.mockImplementation(
      (resource: string, params: { params?: Record<string, unknown> }) => {
        if (resource === 'document-layouts' && params.params?.module === 'quotes') {
          return Promise.resolve({
            items: [DEFAULT_LAYOUT_ITEM],
            pagination: { offset: 0, limit: 25, total: 1 },
            export_link: null,
          })
        }
        return Promise.resolve(EMPTY_PAGE)
      },
    )

    render(
      <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
        <QuoteFormBody
          mode={{ type: 'create', params: { opportunity_id: 55 } }}
          onSuccess={vi.fn()}
          onCancel={vi.fn()}
          initialCode=""
        />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    await waitFor(() =>
      expect(screen.getByRole('combobox', { name: 'Layout' })).toHaveTextContent('Offerta economica'),
    )

    fireEvent.change(screen.getByLabelText('Code'), { target: { value: 'QUO-0001' } })
    fireEvent.change(screen.getByLabelText('Title'), { target: { value: 'Quote with default layout' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createQuote).toHaveBeenCalled())
    expect(vi.mocked(createQuote).mock.calls[0][0]).toMatchObject({ layout_id: 12 })
  })
})

describe('QuoteLayoutSection — edit mode (AC-312)', () => {
  it('shows the persisted layout even when it has since been deactivated', () => {
    const quote = quoteFixture({ layout_id: 41, layout: { id: 41, name: 'Layout disattivato' } })

    render(
      <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
        <QuoteFormBody mode={{ type: 'edit', quote }} onSuccess={vi.fn()} onCancel={vi.fn()} />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    expect(screen.getByRole('combobox', { name: 'Layout' })).toHaveTextContent('Layout disattivato')
  })
})
