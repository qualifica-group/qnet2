import type { ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductCategoryForm } from '@/features/product-categories/product-category-form'
import type { AttributeLayoutData, ProductCategoryDetailWithPermissions, ProductCategoryTreeNode } from '@/features/product-categories/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0062 relocation: the attribute-layout configurator moved from the
 * read-only category detail to the EDIT form, mounted only when a saved
 * category exists (edit mode) — create shows a compact hint instead. Its
 * own fetch/edit/save wiring is covered by
 * `product-category-attribute-layout-editor.test.tsx`; this suite only
 * asserts the mount condition and that it sits outside the RHF `<form>` (its
 * Save is an independent `PUT`, never the category form's submit).
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
  fetchAttributeLayoutMock.mockResolvedValue({ layout: null, attributes: [] })
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissivePermissions() })
})

describe('ProductCategoryFormBody — attribute-layout mount (spec 0062)', () => {
  it('edit mode: mounts the layout editor, OUTSIDE the category form’s <form> element', async () => {
    render(
      <ProductCategoryForm mode={{ type: 'edit', category: category() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    const saveLayoutButton = await screen.findByRole('button', { name: 'Save layout' })
    expect(saveLayoutButton.closest('form')).toBeNull()

    // The category form's own submit button IS inside a <form>.
    const saveButton = screen.getByRole('button', { name: 'Save' })
    expect(saveButton.closest('form')).not.toBeNull()

    await waitFor(() => expect(fetchAttributeLayoutMock).toHaveBeenCalledWith(4, 'product', 'create'))
  })

  it('create mode: does not mount the editor, shows a compact hint instead', async () => {
    render(<ProductCategoryForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await screen.findByRole('button', { name: 'Save' })

    expect(screen.queryByRole('button', { name: 'Save layout' })).not.toBeInTheDocument()
    expect(fetchAttributeLayoutMock).not.toHaveBeenCalled()
    expect(
      screen.getByText('Save the category to configure the attribute layout.'),
    ).toBeInTheDocument()
  })
})
