import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductCategoryDetailView } from '@/features/product-categories/product-category-detail'
import { ProductCategoryForm } from '@/features/product-categories/product-category-form'
import {
  applyRow,
  categoryWrapper,
  category,
  childCategory,
  pencilOf,
  permissionsFor,
  saveRow,
  treeNode,
} from '@/features/product-categories/product-category-test-fixtures'
import type { AttributeLayoutData, ProductCategoryDetailWithPermissions, ProductCategoryTreeNode } from '@/features/product-categories/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * The category create form (a replica of the detail, spec 0229) and the detail's
 * block rows, with the real controls.
 *
 * Spec 0062 revision: the attribute-layout configurator lives OFF the category
 * form, in a dedicated Sheet opened by a row action on the Product Categories
 * table (`ProductCategoryAttributeLayoutSheet`); its own fetch/edit/save wiring
 * is covered by `product-category-attribute-layout-editor.test.tsx`.
 *
 * The inherit-from-parent switch is per usage context and lives INSIDE the
 * attributes editor, so the three barriers move independently.
 */

const updateProductCategoryMock = vi.fn()
const fetchProductCategoryTreeMock = vi.fn<() => Promise<ProductCategoryTreeNode[]>>()
const fetchAttributeLayoutMock = vi.fn<
  (categoryId: number, context: string, formMode: string) => Promise<AttributeLayoutData>
>()
const fetchEffectiveManagerLabelsMock = vi.fn<(categoryId: number) => Promise<Record<string, string>>>()

vi.mock('@/features/product-categories/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/product-categories/api')>()),
  createProductCategory: vi.fn(),
  updateProductCategory: (...args: unknown[]) => updateProductCategoryMock(...args),
  fetchProductCategoryTree: () => fetchProductCategoryTreeMock(),
  fetchEffectiveAttributes: () => Promise.resolve([]),
  fetchEffectiveManagerLabels: (categoryId: number) => fetchEffectiveManagerLabelsMock(categoryId),
  fetchAttributeLayout: (...args: [number, string, string]) => fetchAttributeLayoutMock(...args),
  saveAttributeLayout: vi.fn(),
  // The reportable-switch suite below flips the category into "effectively
  // reportable" live, which mounts `ProductCategoryReportColumnsField`.
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

function wrapper() {
  return categoryWrapper().wrapper
}

function renderDetail(detail: ProductCategoryDetailWithPermissions) {
  return render(<ProductCategoryDetailView category={detail} />, { wrapper: wrapper() })
}

function renderCreate() {
  return render(<ProductCategoryForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
    wrapper: wrapper(),
  })
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
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
})

describe('create form — replica of the detail (spec 0229 D-4)', () => {
  it('does not mount the layout editor', async () => {
    renderCreate()

    await screen.findAllByRole('button', { name: 'Save' })

    expect(screen.queryByRole('button', { name: 'Save layout' })).not.toBeInTheDocument()
    expect(fetchAttributeLayoutMock).not.toHaveBeenCalled()
  })

  it('shows the header rows closed, each with its pencil, and the Details rows are not inputs', async () => {
    renderCreate()

    expect(await screen.findByRole('button', { name: 'Edit Name' })).toBeInTheDocument()
    expect(screen.queryByRole('textbox', { name: /^Name/ })).not.toBeInTheDocument()
    expect(pencilOf('parent')).toBeInTheDocument()
    expect(pencilOf('description')).toBeInTheDocument()
    expect(pencilOf('businessFunction')).toBeInTheDocument()
  })

  it('the identity band follows the name being typed (Done keeps it in the draft)', async () => {
    renderCreate()

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Name' }))
    fireEvent.change(screen.getByRole('textbox', { name: /^Name/ }), { target: { value: 'Printers' } })
    applyRow()

    expect(await screen.findByRole('heading', { name: 'Printers' })).toBeInTheDocument()
  })

  it('groups the rules under one section, one closed row each', async () => {
    renderCreate()

    await screen.findAllByRole('button', { name: 'Save' })
    const rules = screen.getByRole('heading', { name: 'Management rules' }).closest('section') as HTMLElement

    for (const key of [
      'requiresQuote',
      'managementMode',
      'singleQuotePerOpportunity',
      'generatesContract',
      'multiQuoteWorkOrder',
      'simplifiedOfferLine',
      'isSelectable',
      'isActive',
      'isReportable',
    ]) {
      expect(within(rules).getByRole('button', { name: `Edit ${i18n.t(`productCategories.form.${key}`)}` })).toBeInTheDocument()
    }
    expect(within(rules).queryAllByRole('switch')).toHaveLength(0)
  })

  it('shows the attributes and the manager labels editors open', async () => {
    renderCreate()

    await screen.findAllByRole('button', { name: 'Save' })

    expect(screen.getByRole('heading', { name: 'Product attributes' })).toBeInTheDocument()
    for (const n of [1, 2, 3, 4]) {
      const input = screen.getByRole('textbox', { name: `A.M. ${n}` })
      expect(input).toHaveAttribute('placeholder', `Account manager ${n}`)
      expect(input).toHaveValue('')
    }
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

describe('attributes block row — per-context inheritance switches', () => {
  it('renders one switch per context and toggles them independently', async () => {
    renderDetail(childCategory())

    fireEvent.click(pencilOf('sections.attributes.title') as HTMLElement)
    await screen.findByRole('heading', { name: 'Product attributes' })

    const productSwitch = within(attributeSection('Product attributes')).getByRole('switch')
    const quoteSwitch = within(attributeSection('Quote attributes')).getByRole('switch')
    expect(productSwitch).toBeChecked()
    expect(quoteSwitch).toBeChecked()

    fireEvent.click(productSwitch)

    await waitFor(() => expect(productSwitch).not.toBeChecked())
    expect(quoteSwitch).toBeChecked()
  })

  it('root category: no inheritance switch at all (no ancestry to inherit from)', async () => {
    renderDetail(category())

    fireEvent.click(pencilOf('sections.attributes.title') as HTMLElement)
    await screen.findByRole('heading', { name: 'Product attributes' })

    expect(screen.queryAllByRole('switch', { name: 'Inherit from parent' })).toHaveLength(0)
  })

  it('saving the toggled barrier sends it alone', async () => {
    const saved = childCategory({ inherits_product_attributes: false })
    updateProductCategoryMock.mockResolvedValue(saved)
    renderDetail(childCategory())

    fireEvent.click(pencilOf('sections.attributes.title') as HTMLElement)
    await screen.findByRole('heading', { name: 'Product attributes' })
    fireEvent.click(within(attributeSection('Product attributes')).getByRole('switch'))
    saveRow()

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    expect(updateProductCategoryMock).toHaveBeenCalledWith(4, { inherits_product_attributes: false })
  })
})

function reportableTreeNode(overrides: Partial<ProductCategoryTreeNode>): ProductCategoryTreeNode {
  return treeNode({ is_reportable: null, ...overrides })
}

describe('reportable row (inherited, can be forced)', () => {
  it('create mode: the switch is off by default and can be turned on', async () => {
    renderCreate()

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Visible in reports' }))
    const reportableSwitch = await screen.findByRole('switch', { name: 'Visible in reports' })
    expect(reportableSwitch).not.toBeChecked()

    fireEvent.click(reportableSwitch)

    await waitFor(() => expect(reportableSwitch).toBeChecked())
  })

  it('edit mode: a child inherits a reportable parent, and switching it off forces it', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue([reportableTreeNode({ id: 1, name: 'Electronics', is_reportable: true })])

    renderDetail(
      childCategory({
        is_reportable: null,
        effective_is_reportable: true,
        is_reportable_source_category: { id: 1, name: 'Electronics' },
      }),
    )

    fireEvent.click(pencilOf('isReportable') as HTMLElement)
    const reportableSwitch = await screen.findByRole('switch', { name: 'Visible in reports' })
    await waitFor(() => expect(reportableSwitch).toBeChecked())
    expect(reportableSwitch).toBeEnabled()
    expect(screen.getByText(/Inherited from "Electronics"\. Change it to force it/)).toBeInTheDocument()

    fireEvent.click(reportableSwitch)

    await waitFor(() => expect(reportableSwitch).not.toBeChecked())
    expect(screen.getByText('Forced')).toBeInTheDocument()
    expect(screen.getByText(/Forced on this category: it no longer follows "Electronics"/)).toBeInTheDocument()

    // Back to the parent's value: inheriting again, no longer forced.
    fireEvent.click(reportableSwitch)

    await waitFor(() => expect(reportableSwitch).toBeChecked())
    expect(screen.queryByText('Forced')).not.toBeInTheDocument()
  })

  it('turning it on under a non-reportable branch makes it the starting point, not a forced value', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue([reportableTreeNode({ id: 1, name: 'Electronics' })])

    renderDetail(childCategory({ is_reportable: null }))

    fireEvent.click(pencilOf('isReportable') as HTMLElement)
    expect(await screen.findByText(/No parent category is visible in the reports/)).toBeInTheDocument()

    const reportableSwitch = screen.getByRole('switch', { name: 'Visible in reports' })
    fireEvent.click(reportableSwitch)

    await waitFor(() => expect(reportableSwitch).toBeChecked())
    expect(screen.getByText(/Set on this category: every subcategory inherits it/)).toBeInTheDocument()
    expect(screen.queryByText('Forced')).not.toBeInTheDocument()
  })

  it('each rule carries its info glyph once its row is open', async () => {
    renderDetail(category())

    for (const [key, name] of [
      ['requiresQuote', 'Quoted'],
      ['managementMode', 'Management mode'],
      ['singleQuotePerOpportunity', 'One offer per opportunity'],
      ['isSelectable', 'Selectable'],
      ['isReportable', 'Visible in reports'],
    ]) {
      fireEvent.click(pencilOf(key) as HTMLElement)
      expect(await screen.findByRole('button', { name: `More info about ${name}` })).toBeInTheDocument()
    }
  })
})

describe('manager labels block row (spec 0080)', () => {
  it("edit mode: the row opens prefilled with the category's own labels (AC-040)", async () => {
    renderDetail(category({ manager_labels: { '1': 'Commercial', '3': 'Consultant' } }))

    fireEvent.click(pencilOf('sections.managerLabels.title') as HTMLElement)

    expect(await screen.findByRole('textbox', { name: 'A.M. 1' })).toHaveValue('Commercial')
    expect(screen.getByRole('textbox', { name: 'A.M. 3' })).toHaveValue('Consultant')
    expect(screen.getByRole('textbox', { name: 'A.M. 2' })).toHaveValue('')
  })

  it('disabling the inherit toggle drops the inherited preview immediately, before saving (AC-041)', async () => {
    fetchEffectiveManagerLabelsMock.mockResolvedValue({ '2': 'Operator' })

    renderDetail(childCategory())

    fireEvent.click(pencilOf('sections.managerLabels.title') as HTMLElement)
    await screen.findByText('Operator')

    const toggle = screen.getByRole('switch', { name: 'Inherit from parent' })
    expect(toggle).toBeChecked()

    fireEvent.click(toggle)

    await waitFor(() => expect(screen.queryByText('Operator')).not.toBeInTheDocument())
  })

  // Spec 0080 amendment A1.
  it('"Add level" appends a 5th row, and resetting it removes only that row (AC-050, AC-054, AC-055)', async () => {
    renderCreate()

    await screen.findAllByRole('button', { name: 'Save' })
    expect(screen.queryByRole('textbox', { name: 'A.M. 5' })).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Add level' }))

    const fifthRow = await screen.findByRole('textbox', { name: 'A.M. 5' })
    fireEvent.change(fifthRow, { target: { value: 'Regional lead' } })
    expect(fifthRow).toHaveValue('Regional lead')

    fireEvent.click(screen.getByRole('button', { name: 'Reset A.M. 5 to the default label' }))

    await waitFor(() => expect(screen.queryByRole('textbox', { name: 'A.M. 5' })).not.toBeInTheDocument())
    // The other rows are untouched by the reset.
    expect(screen.getByRole('textbox', { name: 'A.M. 1' })).toHaveValue('')
  })

  it('"Add level" is disabled once all 12 positions are configured (AC-055)', async () => {
    const twelvePositions = Object.fromEntries(Array.from({ length: 12 }, (_, index) => [String(index + 1), '']))
    renderDetail(category({ manager_labels: twelvePositions }))

    fireEvent.click(pencilOf('sections.managerLabels.title') as HTMLElement)

    expect(await screen.findByRole('textbox', { name: 'A.M. 12' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Add level' })).toBeDisabled()
  })
})
