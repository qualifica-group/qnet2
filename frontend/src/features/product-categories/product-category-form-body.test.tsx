import type { ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductCategoryForm } from '@/features/product-categories/product-category-form'
import type { AttributeLayoutData, ProductCategoryDetailWithPermissions, ProductCategoryTreeNode } from '@/features/product-categories/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0062 revision: the attribute-layout configurator moved OFF the
 * category form entirely, into a dedicated Sheet opened by a row action on
 * the Product Categories table (`ProductCategoryAttributeLayoutSheet`). This
 * suite asserts the negative: the form never mounts the layout editor, in
 * either mode. The editor's own fetch/edit/save wiring is covered by
 * `product-category-attribute-layout-editor.test.tsx`.
 *
 * Second suite below: the inherit-from-parent switch is per usage context and
 * lives INSIDE the section it governs, so the two barriers move independently.
 */

const fetchProductCategoryTreeMock = vi.fn<() => Promise<ProductCategoryTreeNode[]>>()
const fetchAttributeLayoutMock = vi.fn<
  (categoryId: number, context: string, formMode: string) => Promise<AttributeLayoutData>
>()

vi.mock('@/features/product-categories/api', () => ({
  createProductCategory: vi.fn(),
  updateProductCategory: vi.fn(),
  fetchProductCategoryTree: () => fetchProductCategoryTreeMock(),
  fetchEffectiveAttributes: () => Promise.resolve([]),
  fetchAttributeLayout: (...args: [number, string, string]) => fetchAttributeLayoutMock(...args),
  saveAttributeLayout: vi.fn(),
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
    inherits_opportunity_attributes: true,
    description: null,
    attributes: [],
    inherited_attributes: [],
    created_at: '2026-01-01T00:00:00Z',
    business_function_id: null,
    business_function: null,
    effective_business_function: null,
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
  fetchAttributeLayoutMock.mockResolvedValue({ layout: null, inherited: null, attributes: [] })
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissivePermissions() })
})

describe('ProductCategoryFormBody — attribute-layout NOT mounted (spec 0062 revision)', () => {
  it('edit mode: does not mount the layout editor', async () => {
    render(
      <ProductCategoryForm mode={{ type: 'edit', category: category() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    // The category form's own submit button IS inside a <form>.
    const saveButton = await screen.findByRole('button', { name: 'Save' })
    expect(saveButton.closest('form')).not.toBeNull()

    expect(screen.queryByRole('button', { name: 'Save layout' })).not.toBeInTheDocument()
    await waitFor(() => expect(fetchAttributeLayoutMock).not.toHaveBeenCalled())
  })

  it('create mode: does not mount the layout editor', async () => {
    render(<ProductCategoryForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await screen.findByRole('button', { name: 'Save' })

    expect(screen.queryByRole('button', { name: 'Save layout' })).not.toBeInTheDocument()
    expect(fetchAttributeLayoutMock).not.toHaveBeenCalled()
  })
})

/** The attribute section whose heading is $title (the heading's grandparent is the section root). */
function attributeSection(title: string): HTMLElement {
  const section = screen.getByRole('heading', { name: title }).closest('div')?.parentElement
  if (section === null || section === undefined) {
    throw new Error(`Attribute section "${title}" not found`)
  }
  return section
}

describe('ProductCategoryFormBody — per-context inheritance switches', () => {
  it('renders one switch per section and toggles the two contexts independently', async () => {
    render(
      <ProductCategoryForm
        mode={{ type: 'edit', category: category({ parent_id: 1, parent: { id: 1, name: 'Electronics' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await screen.findByRole('button', { name: 'Save' })

    const productSwitch = within(attributeSection('Product attributes')).getByRole('switch')
    const opportunitySwitch = within(attributeSection('Opportunity attributes')).getByRole('switch')
    expect(productSwitch).toBeChecked()
    expect(opportunitySwitch).toBeChecked()

    fireEvent.click(productSwitch)

    await waitFor(() => expect(productSwitch).not.toBeChecked())
    expect(opportunitySwitch).toBeChecked()
  })

  it('root category: no inheritance switch at all (no ancestry to inherit from)', async () => {
    render(
      <ProductCategoryForm mode={{ type: 'edit', category: category() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await screen.findByRole('button', { name: 'Save' })

    expect(screen.queryAllByRole('switch')).toHaveLength(0)
  })
})
