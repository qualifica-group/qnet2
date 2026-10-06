import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { QuoteFormBody } from '@/features/quotes/quote-form-body'
import { clickCreateSave, rowValue } from '@/features/quotes/quote-test-helpers'
import { createQuote } from '@/features/quotes/api'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0070 AC-310: the create draft's Layout row precompiles with the
 * `quotes` module's active default (without the user picking it) and the
 * create payload carries it. Only the HTTP layer is mocked. The detail's
 * side (AC-312, a deactivated layout still shown) lives in
 * `quote-detail.test.tsx`/`quote-detail-inline-edit.test.tsx`.
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

/**
 * Spec 0102 AC-001: creation now requires at least one real offer line. Seeds
 * it via the deep-link `product_ids` mechanism (`quote-form-body.tsx`, user
 * directive 2026-08-31, already exercised by `quote-form-seeded-products
 * .test.tsx`) rather than driving the product-picker UI: this avoids the
 * unrelated unlock-dialog and commission-defaults machinery those tests
 * would pull in for something this file isn't testing.
 */
const SEEDED_PRODUCT_ITEM = {
  id: 42,
  label: 'Widget Pro',
  meta: {
    code: 'WGT',
    price: '10.00',
    cost: '5.00',
    vat_rate_id: null,
    vat_rate_name: null,
    vat_rate: null,
    unit_of_measure: null,
    product_typology: null,
  },
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

describe('Quote create form — default layout (spec 0070 AC-310)', () => {
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
        <QuoteFormBody mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} initialCode="QUO-0001" />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(rowValue('Layout')).toContain('Offerta economica'))
  })

  it('carries the precompiled layout_id in the create payload', async () => {
    fetchForSelectMock.mockImplementation(
      (resource: string, params: { params?: Record<string, unknown>; ids?: number[] }) => {
        if (resource === 'document-layouts' && params.params?.module === 'quotes') {
          return Promise.resolve({
            items: [DEFAULT_LAYOUT_ITEM],
            pagination: { offset: 0, limit: 25, total: 1 },
            export_link: null,
          })
        }
        if (resource === 'products' && params.ids?.includes(SEEDED_PRODUCT_ITEM.id)) {
          return Promise.resolve({
            items: [SEEDED_PRODUCT_ITEM],
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
          mode={{ type: 'create', params: { opportunity_id: 55, product_ids: String(SEEDED_PRODUCT_ITEM.id) } }}
          onSuccess={vi.fn()}
          onCancel={vi.fn()}
          initialCode="QUO-0001"
        />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(rowValue('Layout')).toContain('Offerta economica'))
    // Spec 0102 AC-001: wait for the deep-link seeded row before submitting,
    // or the create schema rejects on an empty `offer_lines` (AC-040).
    await waitFor(() => expect(screen.getByLabelText('Row 1 quantity')).toHaveValue(1))

    clickCreateSave()

    await waitFor(() => expect(createQuote).toHaveBeenCalled())
    expect(vi.mocked(createQuote).mock.calls[0][0]).toMatchObject({ layout_id: 12 })
  })
})
