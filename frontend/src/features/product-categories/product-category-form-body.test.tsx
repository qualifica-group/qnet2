import type { ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
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
    inherits_attributes: true,
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
