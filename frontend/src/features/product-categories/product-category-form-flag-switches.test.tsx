import type { ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductCategoryForm } from '@/features/product-categories/product-category-form'
import type { AttributeLayoutData, ProductCategoryDetailWithPermissions, ProductCategoryTreeNode } from '@/features/product-categories/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'

/**
 * The per-node flag switches of the rules section, split out of
 * `product-category-form-body.test.tsx` (engineering.md §6 file limit), with
 * the same form setup:
 *  - "Active" (spec 0208 AC-022): on by default in create mode, and under an
 *    inactive ancestor it names that ancestor, since the category is then
 *    inactive whatever its own flag says (D-1);
 *  - "Selectable" (spec 0074): on by default, never inherited.
 */

const fetchProductCategoryTreeMock = vi.fn<() => Promise<ProductCategoryTreeNode[]>>()
const fetchAttributeLayoutMock = vi.fn<
  (categoryId: number, context: string, formMode: string) => Promise<AttributeLayoutData>
>()
const fetchEffectiveManagerLabelsMock = vi.fn<(categoryId: number) => Promise<Record<string, string>>>()

vi.mock('@/features/product-categories/api', () => ({
  createProductCategory: vi.fn(),
  updateProductCategory: vi.fn(),
  fetchProductCategoryTree: () => fetchProductCategoryTreeMock(),
  fetchEffectiveAttributes: () => Promise.resolve([]),
  fetchEffectiveManagerLabels: (categoryId: number) => fetchEffectiveManagerLabelsMock(categoryId),
  fetchAttributeLayout: (...args: [number, string, string]) => fetchAttributeLayoutMock(...args),
  saveAttributeLayout: vi.fn(),
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

// The parent-category picker's quick-create "+" reads `useAbilities()` via
// `Can`, which needs an `AuthProvider` this suite doesn't render (mirrors
// `product-category-business-function-field.test.tsx`).
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const FULL_ACCESS: ResourcePermissions['resource'] = {
  view: true,
  create: true,
  update: true,
  delete: true,
  export: true,
  import: true,
}

function permissivePermissions(): ResourcePermissions {
  return { resource: FULL_ACCESS, fields: {}, actions: {} }
}

function category(
  overrides: Partial<ProductCategoryDetailWithPermissions> = {},
): ProductCategoryDetailWithPermissions {
  return {
    id: 4,
    name: 'Laptops',
    parent_id: null,
    parent: null,
    inherits_product_attributes: true,
    inherits_quote_attributes: true,
    inherits_work_order_attributes: true,
    description: null,
    attributes: [],
    inherited_attributes: [],
    created_at: '2026-01-01T00:00:00Z',
    business_function_id: null,
    requires_quote: false,
    business_function: null,
    effective_business_function: null,
    requires_quote_source_category: null,
    is_selectable: true,
    is_active: true,
    is_reportable: false,
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
    simplified_offer_line_override: null,
    simplified_offer_line_source_category: null,
    manager_labels: {},
    inherits_manager_labels: true,
    inherited_manager_labels: {},
    permissions: permissivePermissions(),
    ...overrides,
  }
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
  fetchProductCategoryTreeMock.mockReset()
  fetchProductCategoryTreeMock.mockResolvedValue([])
  fetchAttributeLayoutMock.mockReset()
  fetchAttributeLayoutMock.mockResolvedValue({ layout: null, inherited: null, inherited_from_category: null, inherited_from_category_source: null, attributes: [] })
  fetchEffectiveManagerLabelsMock.mockReset()
  fetchEffectiveManagerLabelsMock.mockResolvedValue({})
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissivePermissions() })
})

function reportableTreeNode(overrides: Partial<ProductCategoryTreeNode>): ProductCategoryTreeNode {
  return {
    id: 1,
    name: 'Node',
    parent_id: null,
    children: [],
    attributes_count: 0,
    products_count: 0,
    business_function_id: null,
    requires_quote: false,
    is_selectable: true,
    is_active: true,
    is_reportable: null,
    management_mode: 'multiple',
    single_quote_per_opportunity: false,
    generates_contract: true,
    simplified_offer_line: false,
    simplified_offer_line_override: null,
    ...overrides,
  }
}

describe('ProductCategoryFormBody — active switch (spec 0208 AC-022)', () => {
  it('create mode: the switch is on by default and no inheritance notice shows', async () => {
    render(<ProductCategoryForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await screen.findAllByRole('button', { name: 'Save' })

    expect(screen.getByRole('switch', { name: 'Active' })).toBeChecked()
    expect(screen.queryByText(/Not active because/)).not.toBeInTheDocument()
  })

  it('names the inactive ancestor under the switch', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue([
      reportableTreeNode({ id: 1, name: 'Electronics', is_active: false }),
    ])

    render(
      <ProductCategoryForm
        mode={{
          type: 'edit',
          category: category({ parent_id: 1, parent: { id: 1, name: 'Electronics' } }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await screen.findAllByRole('button', { name: 'Save' })

    expect(await screen.findByText('Not active because "Electronics" is not')).toBeInTheDocument()
    expect(screen.getByRole('switch', { name: 'Active' })).toBeChecked()
  })
})

describe('ProductCategoryFormBody — selectable switch (spec 0074)', () => {
  it('create mode: the switch is on by default and can be turned off (AC-014)', async () => {
    render(<ProductCategoryForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await screen.findAllByRole('button', { name: 'Save' })

    const selectableSwitch = screen.getByRole('switch', { name: 'Selectable' })
    expect(selectableSwitch).toBeChecked()

    fireEvent.click(selectableSwitch)

    await waitFor(() => expect(selectableSwitch).not.toBeChecked())
  })

  it('edit mode: the switch mirrors the saved value, with no parent-driven read-only state', async () => {
    render(
      <ProductCategoryForm
        mode={{
          type: 'edit',
          category: category({ parent_id: 1, parent: { id: 1, name: 'Electronics' }, is_selectable: false }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await screen.findAllByRole('button', { name: 'Save' })

    const selectableSwitch = screen.getByRole('switch', { name: 'Selectable' })
    expect(selectableSwitch).not.toBeChecked()
    // Unlike the quote flag, this one is never inherited: a child still edits it.
    expect(selectableSwitch).toBeEnabled()
  })
})
