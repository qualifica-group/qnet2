import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { ProductCategoryDetailView } from '@/features/product-categories/product-category-detail'
import {
  READ_ONLY,
  cancelRow,
  category,
  categoryWrapper,
  childCategory,
  pencilOf,
  permissionsFor,
  saveRow,
  treeNode,
} from '@/features/product-categories/product-category-test-fixtures'
import type {
  ProductCategoryDetailWithPermissions,
  ProductCategoryTreeNode,
} from '@/features/product-categories/types'

/**
 * Spec 0229: the product category detail edits in place, one row at a time.
 * Each save is a PATCH of what that row changed, nothing else; the attributes
 * and G.A. denominations editors are ONE full-width block row each.
 */

const updateProductCategoryMock = vi.fn()
const fetchProductCategoryTreeMock = vi.fn<() => Promise<ProductCategoryTreeNode[]>>()

vi.mock('@/features/product-categories/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/product-categories/api')>()),
  updateProductCategory: (...args: unknown[]) => updateProductCategoryMock(...args),
  fetchProductCategoryTree: () => fetchProductCategoryTreeMock(),
  fetchEffectiveAttributes: () => Promise.resolve([]),
  fetchEffectiveManagerLabels: () => Promise.resolve({}),
  fetchReportColumnsCatalog: () => Promise.resolve([]),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/attributes/use-attribute-catalog', () => ({
  useAttributeCatalog: () => ({ data: [], isPending: false, isError: false, refetch: vi.fn() }),
}))

vi.mock('@/features/product-categories/product-category-attribute-layout-preview', () => ({
  ProductCategoryAttributeLayoutPreview: () => null,
}))

function renderDetail(detail: ProductCategoryDetailWithPermissions) {
  const { wrapper } = categoryWrapper()
  return render(<ProductCategoryDetailView category={detail} />, { wrapper })
}

const label = (key: string) => i18n.t(`productCategories.form.${key}`)

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateProductCategoryMock.mockReset()
  updateProductCategoryMock.mockImplementation(async () => category())
  fetchProductCategoryTreeMock.mockReset()
  fetchProductCategoryTreeMock.mockResolvedValue([])
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
})

describe('ProductCategoryDetailView — rows edit in place (spec 0229)', () => {
  it('has no Edit button: the rows are closed, each with its pencil', () => {
    renderDetail(category())

    expect(screen.queryByRole('button', { name: /^Edit$/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf('name')).toBeInTheDocument()
    expect(pencilOf('requiresQuote')).toBeInTheDocument()
    expect(pencilOf('attributes')).toBeInTheDocument()
  })

  it('Save sends the edited field alone', async () => {
    renderDetail(category())

    fireEvent.click(pencilOf('name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox', { name: /^Name/ }), { target: { value: 'Laptops Pro' } })
    saveRow()

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    expect(updateProductCategoryMock).toHaveBeenCalledWith(4, { name: 'Laptops Pro' })
  })

  it('a rule row sends only its flag', async () => {
    renderDetail(category())

    fireEvent.click(pencilOf('requiresQuote') as HTMLElement)
    fireEvent.click(await screen.findByRole('switch', { name: 'Quoted' }))
    saveRow()

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    expect(updateProductCategoryMock).toHaveBeenCalledWith(4, { requires_quote: true })
  })

  it('Cancel restores the row without calling the API', () => {
    renderDetail(category())

    fireEvent.click(pencilOf('name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox', { name: /^Name/ }), { target: { value: 'Renamed' } })
    cancelRow()

    expect(screen.queryByText('Renamed')).not.toBeInTheDocument()
    expect(updateProductCategoryMock).not.toHaveBeenCalled()
  })

  it('offers no pencil without the update permission', () => {
    renderDetail(category({ permissions: permissionsFor({}, false) }))

    expect(pencilOf('name')).not.toBeInTheDocument()
    expect(pencilOf('requiresQuote')).not.toBeInTheDocument()
    expect(pencilOf('attributes')).not.toBeInTheDocument()
  })

  it('offers no pencil on a field the actor may not edit', () => {
    renderDetail(category({ permissions: permissionsFor({ name: READ_ONLY }) }))

    expect(pencilOf('name')).not.toBeInTheDocument()
    expect(pencilOf('description')).toBeInTheDocument()
  })

  it('a child mirrors the root-owned rules: they name the root and offer no pencil', () => {
    renderDetail(childCategory({ requires_quote: true }))

    expect(pencilOf('requiresQuote')).not.toBeInTheDocument()
    expect(pencilOf('managementMode')).not.toBeInTheDocument()
    expect(pencilOf('singleQuotePerOpportunity')).not.toBeInTheDocument()
    expect(pencilOf('generatesContract')).not.toBeInTheDocument()
    expect(screen.getAllByText('Inherited from Electronics').length).toBeGreaterThan(0)
    expect(pencilOf('isSelectable')).toBeInTheDocument()
    expect(pencilOf('simplifiedOfferLine')).toBeInTheDocument()
  })

  it('a child forces the simplified offer line: only the override is sent', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue([
      treeNode({
        id: 1,
        name: 'Electronics',
        children: [treeNode({ id: 4, name: 'Laptops', parent_id: 1 })],
      }),
    ])
    renderDetail(
      childCategory({ simplified_offer_line_source_category: { id: 1, name: 'Electronics' } }),
    )

    fireEvent.click(pencilOf('simplifiedOfferLine') as HTMLElement)
    fireEvent.click(await screen.findByRole('switch', { name: label('simplifiedOfferLine') }))
    saveRow()

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    expect(updateProductCategoryMock).toHaveBeenCalledWith(4, { simplified_offer_line_override: true })
  })

  it('the report columns row appears only while the category is effectively reportable', () => {
    renderDetail(category())
    expect(pencilOf('reportColumns')).not.toBeInTheDocument()
  })

  it('the report columns row is there for a reportable category', () => {
    renderDetail(category({ is_reportable: true, effective_is_reportable: true }))
    expect(pencilOf('reportColumns')).toBeInTheDocument()
  })
})

describe('ProductCategoryDetailView — block rows (spec 0229 D-5)', () => {
  it('the manager labels row saves the labels (and the barrier) alone', async () => {
    renderDetail(category())

    fireEvent.click(pencilOf('sections.managerLabels.title') as HTMLElement)
    fireEvent.change(await screen.findByRole('textbox', { name: 'A.M. 1' }), { target: { value: 'Senior' } })
    saveRow()

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    expect(updateProductCategoryMock).toHaveBeenCalledWith(4, { manager_labels: { '1': 'Senior' } })
  })

  it('the attributes row opens the editor of the three contexts and saves nothing unchanged', async () => {
    renderDetail(category())

    fireEvent.click(pencilOf('sections.attributes.title') as HTMLElement)
    expect(await screen.findByText('Product attributes')).toBeInTheDocument()
    saveRow()

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    expect(updateProductCategoryMock).toHaveBeenCalledWith(4, {})
  })

  it('opening another row discards the unconfirmed edit of the first', () => {
    renderDetail(category())

    fireEvent.click(pencilOf('name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox', { name: /^Name/ }), { target: { value: 'Half typed' } })
    fireEvent.click(pencilOf('description') as HTMLElement)

    const nameRow = screen.getByText('Laptops', { selector: 'span' })
    expect(within(nameRow.closest('div') as HTMLElement).queryByText('Half typed')).not.toBeInTheDocument()
    expect(updateProductCategoryMock).not.toHaveBeenCalled()
  })
})
