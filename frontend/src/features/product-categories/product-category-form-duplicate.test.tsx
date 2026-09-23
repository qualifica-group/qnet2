import type { ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductCategoryForm } from '@/features/product-categories/product-category-form'
import type { ProductCategoryDetailWithPermissions } from '@/features/product-categories/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'

/**
 * Row action "duplicate": the create form is pre-filled from a source
 * category — the name gets the copy suffix, every other field carries over —
 * and submits through `createProductCategory` (never the update), carrying
 * `layout_source_id` so the server copies the source's attribute layouts.
 */

const createProductCategoryMock = vi.fn()
const updateProductCategoryMock = vi.fn()

vi.mock('@/features/product-categories/api', () => ({
  createProductCategory: (...args: unknown[]) => createProductCategoryMock(...args),
  updateProductCategory: (...args: unknown[]) => updateProductCategoryMock(...args),
  fetchProductCategoryTree: () => Promise.resolve([]),
  fetchEffectiveAttributes: () => Promise.resolve([]),
  fetchEffectiveManagerLabels: () => Promise.resolve({}),
  fetchReportColumnsCatalog: () => Promise.resolve([]),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/attributes/use-attribute-catalog', () => ({
  useAttributeCatalog: () => ({ data: [], isPending: false, isError: false, refetch: vi.fn() }),
}))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

const SOURCE: ProductCategoryDetailWithPermissions = {
  id: 4,
  name: 'Laptops',
  parent_id: null,
  parent: null,
  inherits_product_attributes: true,
  inherits_quote_attributes: false,
  inherits_work_order_attributes: true,
  description: 'Portable computers',
  attributes: [
    { attribute_id: 9, code: 'ram', name: 'RAM', type: 'text', is_required: true, sort_order: 0, context: 'quote' },
  ],
  inherited_attributes: [],
  created_at: '2026-01-01T00:00:00Z',
  business_function_id: null,
  requires_quote: true,
  business_function: null,
  effective_business_function: null,
  requires_quote_source_category: null,
  is_selectable: true,
  is_reportable: null,
  effective_is_reportable: false,
  is_reportable_source_category: null,
  report_columns: null,
  effective_report_columns: [],
  report_columns_source_category: null,
  inherited_report_columns: [],
  inherited_report_columns_source_category: null,
  management_mode: 'multiple',
  single_quote_per_opportunity: false,
  generates_contract: true,
  management_mode_source_category: null,
  single_quote_per_opportunity_source_category: null,
  generates_contract_source_category: null,
  simplified_offer_line: false,
  simplified_offer_line_source_category: null,
  manager_labels: { '1': 'Area manager' },
  inherits_manager_labels: true,
  inherited_manager_labels: {},
  permissions: PERMISSIONS,
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createProductCategoryMock.mockReset()
  createProductCategoryMock.mockResolvedValue({ ...SOURCE, id: 11, name: 'Laptops (copy)' })
  updateProductCategoryMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: PERMISSIONS })
})

describe('ProductCategoryForm — duplicate mode', () => {
  it('pre-fills the name with the copy suffix and renders the create heading', async () => {
    render(<ProductCategoryForm mode={{ type: 'duplicate', source: SOURCE }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    expect(await screen.findByDisplayValue('Laptops (copy)')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: i18n.t('productCategories.form.createTitle') })).toBeInTheDocument()
  })

  it('submits through the create path with the source fields and layout_source_id', async () => {
    const onSuccess = vi.fn()
    render(<ProductCategoryForm mode={{ type: 'duplicate', source: SOURCE }} onSuccess={onSuccess} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await screen.findByDisplayValue('Laptops (copy)')
    fireEvent.click(screen.getAllByRole('button', { name: i18n.t('productCategories.form.save') })[0])

    await waitFor(() => expect(createProductCategoryMock).toHaveBeenCalledTimes(1))
    expect(updateProductCategoryMock).not.toHaveBeenCalled()
    expect(createProductCategoryMock.mock.calls[0][0]).toMatchObject({
      name: 'Laptops (copy)',
      parent_id: null,
      description: 'Portable computers',
      inherits_quote_attributes: false,
      requires_quote: true,
      attributes: [{ attribute_id: 9, context: 'quote', is_required: true, sort_order: 0 }],
      manager_labels: { '1': 'Area manager' },
      layout_source_id: 4,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(expect.objectContaining({ id: 11 })))
  })
})
