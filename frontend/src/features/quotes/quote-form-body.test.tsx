import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { QuoteFormBody } from '@/features/quotes/quote-form-body'
import type { QuoteDetailWithPermissions } from '@/features/quotes/types'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0065: AC-070 (tabs + always-visible summary), AC-077 (a field marked
 * non-editable by permissions renders disabled via `MetaField`), AC-082 (the
 * `code` field is prefilled in create and read-only in edit).
 */

vi.mock('@/features/quotes/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/quotes/api')>('@/features/quotes/api')
  return {
    ...actual,
    createQuote: vi.fn(),
    updateQuote: vi.fn(),
    fetchQuoteNextCode: vi.fn(),
  }
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

// `commercial_id`/`reporter_id`/`supervisor_id` resolve to resources with a
// registered quick-create entry (referents/users): `RelationSelectField`
// renders a `<Can>`-gated "+" button for them, which needs `AuthProvider`.
// Stubbed the same way `quotes-table.test.tsx` already does, so this suite
// stays scoped to `QuoteFormBody` itself rather than the auth stack.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

const READONLY_FIELD: FieldPermission = {
  visible: true,
  hidden: false,
  editable: false,
  readonly: true,
  required: false,
  disabled: true,
}

function quoteFixture(): QuoteDetailWithPermissions {
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

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue(EMPTY_PAGE)
})

describe('QuoteFormBody (spec 0065)', () => {
  it('shows the three tabs and keeps the economic summary visible below them regardless of the active tab (AC-070)', () => {
    render(
      <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
        <QuoteFormBody mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} initialCode="" />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    expect(screen.getByRole('tab', { name: 'Offer' })).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Costs' })).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Notes' })).toBeInTheDocument()
    expect(screen.getByText('Expected revenue')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('tab', { name: 'Costs' }))

    expect(screen.getByText('Expected revenue')).toBeInTheDocument()
  })

  it('prefills the code field with the suggested sequential code in create mode, editable (AC-082)', () => {
    render(
      <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
        <QuoteFormBody mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} initialCode="QUO-0007" />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    const codeInput = screen.getByLabelText('Code') as HTMLInputElement
    expect(codeInput.value).toBe('QUO-0007')
    expect(codeInput).not.toBeDisabled()
  })

  it('shows the persisted code read-only in edit mode, without requesting a next-code (AC-082)', () => {
    const quote = quoteFixture()
    const editPermissions: ResourcePermissions = {
      ...FULL_ACCESS_PERMISSIONS,
      fields: { code: READONLY_FIELD },
    }

    render(
      <ResourcePermissionsProvider permissions={editPermissions}>
        <QuoteFormBody mode={{ type: 'edit', quote: { ...quote, permissions: editPermissions } }} onSuccess={vi.fn()} onCancel={vi.fn()} />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    const codeInput = screen.getByLabelText('Code') as HTMLInputElement
    expect(codeInput.value).toBe('QUO-0009')
    expect(codeInput).toBeDisabled()
  })

  it('disables a field the permissions mark non-editable, via MetaField (AC-077)', () => {
    const permissions: ResourcePermissions = {
      ...FULL_ACCESS_PERMISSIONS,
      fields: { commercial_id: READONLY_FIELD },
    }

    render(
      <ResourcePermissionsProvider permissions={permissions}>
        <QuoteFormBody mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} initialCode="" />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    expect(screen.getByRole('combobox', { name: 'Commercial' })).toBeDisabled()
  })
})
