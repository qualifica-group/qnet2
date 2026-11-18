import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductCategoryDetailView } from '@/features/product-categories/product-category-detail'
import {
  category,
  categoryWrapper,
  childCategory,
  pencilOf,
  permissionsFor,
  treeNode,
} from '@/features/product-categories/product-category-test-fixtures'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import { buildCreatePayload, buildUpdatePayload } from '@/features/product-categories/product-category-form-payload'
import { resolveInheritedRequestRegistryType } from '@/features/product-categories/request-registry-type-inheritance'
import { buildDraftRecordValues } from '@/features/product-categories/product-category-record'
import { editDefaults } from '@/features/product-categories/use-product-category-form'
import type {
  ProductCategoryDetailWithPermissions,
  ProductCategoryTreeNode,
} from '@/features/product-categories/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * Spec 0410: `request_registry_type` ("Anagrafica in Gestione Richieste") belongs to the
 * branch ROOT, like `usage_area`: a root edits and sends it, a child mirrors the root.
 */

const fetchProductCategoryTreeMock = vi.fn<() => Promise<ProductCategoryTreeNode[]>>()

vi.mock('@/features/product-categories/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/product-categories/api')>()),
  fetchProductCategoryTree: () => fetchProductCategoryTreeMock(),
  fetchEffectiveAttributes: () => Promise.resolve([]),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

function renderDetail(detail: ProductCategoryDetailWithPermissions) {
  return render(<ProductCategoryDetailView category={detail} />, { wrapper: categoryWrapper().wrapper })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchProductCategoryTreeMock.mockReset()
  fetchProductCategoryTreeMock.mockResolvedValue([])
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
})

describe('resolveInheritedRequestRegistryType', () => {
  it('resolves the ROOT type for a child pick and null for a root pick', () => {
    const nodesById = indexCategoryTree([
      treeNode({
        id: 1,
        name: 'Consulenza',
        request_registry_type: 'company',
        children: [treeNode({ id: 2, name: 'Finanza agevolata', parent_id: 1, request_registry_type: 'company' })],
      }),
    ])

    expect(resolveInheritedRequestRegistryType(nodesById, 2)).toEqual({
      registryType: 'company',
      sourceCategory: { id: 1, name: 'Consulenza' },
    })
    expect(resolveInheritedRequestRegistryType(nodesById, null)).toBeNull()
  })

  it('a new child draft shows the root type it will inherit', () => {
    const tree = [treeNode({ id: 1, name: 'Consulenza', request_registry_type: 'company' })]

    const values = buildDraftRecordValues({ parent_id: 1, request_registry_type: 'individual' }, tree, () => '')

    expect(values.request_registry_type).toBe('company')
    expect(values.request_registry_type_source_category).toEqual({ id: 1, name: 'Consulenza' })
  })
})

describe('request_registry_type payload', () => {
  it('a root sends it on create, a child never does', () => {
    const root = editDefaults(category({ parent_id: null, parent: null, request_registry_type: 'company' }), {})
    const child = editDefaults(childCategory({ request_registry_type: 'company' }), {})

    expect(buildCreatePayload(root)).toMatchObject({ request_registry_type: 'company' })
    expect(buildCreatePayload(child)).not.toHaveProperty('request_registry_type')
  })

  it('a root sends it on update only when it changed', () => {
    const original = category({ parent_id: null, parent: null })
    const values = editDefaults(original, {})

    expect(buildUpdatePayload(values, original)).not.toHaveProperty('request_registry_type')
    expect(buildUpdatePayload({ ...values, request_registry_type: 'company' }, original)).toEqual({
      request_registry_type: 'company',
    })
  })
})

describe('request_registry_type row', () => {
  it('root category: shows its own type and offers the pencil', () => {
    renderDetail(category({ parent_id: null, parent: null, request_registry_type: 'company' }))

    expect(pencilOf('requestRegistryType')).toBeInTheDocument()
    expect(screen.getByText('Company')).toBeInTheDocument()
  })

  it('root category: the editor names the rule and explains it', async () => {
    renderDetail(category({ parent_id: null, parent: null }))

    fireEvent.click(pencilOf('requestRegistryType') as HTMLElement)

    expect(await screen.findByText(/Which type the new-client card starts on/)).toBeInTheDocument()
  })

  it('child category: mirrors the root, names it and offers no pencil', () => {
    renderDetail(childCategory({ request_registry_type: 'company' }))

    expect(pencilOf('requestRegistryType')).not.toBeInTheDocument()
    // The chip sits in THIS rule's row, next to its value, not only on the other rules.
    const value = screen.getByText('Company')
    expect(within(value.parentElement as HTMLElement).getByText('Inherited from Electronics')).toBeInTheDocument()
  })
})
