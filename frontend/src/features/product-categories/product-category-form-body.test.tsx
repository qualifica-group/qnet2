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
const fetchEffectiveManagerLabelsMock = vi.fn<(categoryId: number) => Promise<Record<string, string>>>()

vi.mock('@/features/product-categories/api', () => ({
  createProductCategory: vi.fn(),
  updateProductCategory: vi.fn(),
  fetchProductCategoryTree: () => fetchProductCategoryTreeMock(),
  fetchEffectiveAttributes: () => Promise.resolve([]),
  fetchEffectiveManagerLabels: (categoryId: number) => fetchEffectiveManagerLabelsMock(categoryId),
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
    management_mode: 'multiple',
    single_quote_per_opportunity: false,
    generates_contract: true,
    management_mode_source_category: null,
    single_quote_per_opportunity_source_category: null,
    generates_contract_source_category: null,
    simplified_offer_line: false,
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

describe('ProductCategoryFormBody — attribute-layout NOT mounted (spec 0062 revision)', () => {
  it('edit mode: does not mount the layout editor', async () => {
    render(
      <ProductCategoryForm mode={{ type: 'edit', category: category() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    // The form's own submit exists twice now (identity bar + footer, spec-wide
    // record-form skeleton): the footer copy is the one INSIDE the <form>, the
    // bar's reaches it through the HTML `form=` attribute.
    const saveButtons = await screen.findAllByRole('button', { name: 'Save' })
    expect(saveButtons.some((button) => button.closest('form') !== null)).toBe(true)

    expect(screen.queryByRole('button', { name: 'Save layout' })).not.toBeInTheDocument()
    await waitFor(() => expect(fetchAttributeLayoutMock).not.toHaveBeenCalled())
  })

  it('create mode: does not mount the layout editor', async () => {
    render(<ProductCategoryForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await screen.findAllByRole('button', { name: 'Save' })

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

/** A whole top-level `FormSection` (its `<section>` root), located by its own h3 title. */
function formSection(title: string): HTMLElement {
  const section = screen.getByRole('heading', { name: title }).closest('section')
  if (section === null) {
    throw new Error(`Form section "${title}" not found`)
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

    await screen.findAllByRole('button', { name: 'Save' })

    const productSwitch = within(attributeSection('Product attributes')).getByRole('switch')
    const quoteSwitch = within(attributeSection('Quote attributes')).getByRole('switch')
    expect(productSwitch).toBeChecked()
    expect(quoteSwitch).toBeChecked()

    fireEvent.click(productSwitch)

    await waitFor(() => expect(productSwitch).not.toBeChecked())
    expect(quoteSwitch).toBeChecked()
  })

  it('root category: no inheritance switch at all (no ancestry to inherit from)', async () => {
    render(
      <ProductCategoryForm mode={{ type: 'edit', category: category() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await screen.findAllByRole('button', { name: 'Save' })

    // Scoped by accessible name: the rules section carries its own, unrelated
    // switches (quote flag, single offer, selectable), which a bare role query
    // would count.
    expect(screen.queryAllByRole('switch', { name: 'Inherit from parent' })).toHaveLength(0)
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

/**
 * User directive 2026-08-07: the four behavioural rules moved out of the
 * identity fields into their own section, each carrying an (i) explanation.
 */
describe('ProductCategoryFormBody — management rules section', () => {
  it('groups the four rules under one section, each with its info glyph', async () => {
    render(<ProductCategoryForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await screen.findAllByRole('button', { name: 'Save' })

    const rules = formSection('Management rules')

    expect(within(rules).getByRole('switch', { name: 'Quoted' })).toBeInTheDocument()
    expect(within(rules).getByRole('combobox')).toBeInTheDocument()
    expect(within(rules).getByRole('switch', { name: 'One offer per opportunity' })).toBeInTheDocument()
    expect(within(rules).getByRole('switch', { name: 'Selectable' })).toBeInTheDocument()

    for (const rule of ['Quoted', 'Management mode', 'One offer per opportunity', 'Selectable']) {
      expect(within(rules).getByRole('button', { name: `More info about ${rule}` })).toBeInTheDocument()
    }
  })

  it('no longer shows the rules inside the identity section', async () => {
    render(<ProductCategoryForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await screen.findAllByRole('button', { name: 'Save' })

    expect(within(formSection('Details')).queryAllByRole('switch')).toHaveLength(0)
  })
})

describe('ProductCategoryFormBody — manager labels section (spec 0080)', () => {
  it('create mode: renders 4 G.A. rows with the default-denomination placeholder (AC-040)', async () => {
    render(<ProductCategoryForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await screen.findAllByRole('button', { name: 'Save' })

    for (const n of [1, 2, 3, 4]) {
      const input = screen.getByRole('textbox', { name: `A.M. ${n}` })
      expect(input).toHaveAttribute('placeholder', `Account manager ${n}`)
      expect(input).toHaveValue('')
    }
  })

  it("edit mode: prefills the rows with the category's own labels (AC-040)", async () => {
    render(
      <ProductCategoryForm
        mode={{ type: 'edit', category: category({ manager_labels: { '1': 'Commercial', '3': 'Consultant' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await screen.findAllByRole('button', { name: 'Save' })

    expect(screen.getByRole('textbox', { name: 'A.M. 1' })).toHaveValue('Commercial')
    expect(screen.getByRole('textbox', { name: 'A.M. 3' })).toHaveValue('Consultant')
    expect(screen.getByRole('textbox', { name: 'A.M. 2' })).toHaveValue('')
  })

  it('disabling the inherit toggle drops the inherited preview immediately, before saving (AC-041)', async () => {
    fetchEffectiveManagerLabelsMock.mockResolvedValue({ '2': 'Operator' })

    render(
      <ProductCategoryForm
        mode={{ type: 'edit', category: category({ parent_id: 1, parent: { id: 1, name: 'Electronics' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await screen.findAllByRole('button', { name: 'Save' })
    await screen.findByText('Operator')

    const toggle = within(formSection('Account managers')).getByRole('switch')
    expect(toggle).toBeChecked()

    fireEvent.click(toggle)

    await waitFor(() => expect(screen.queryByText('Operator')).not.toBeInTheDocument())
  })

  // Spec 0080 amendment A1.
  it('"Add level" appends a 5th row, and resetting it removes only that row (AC-050, AC-054, AC-055)', async () => {
    render(<ProductCategoryForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

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
    render(
      <ProductCategoryForm
        mode={{ type: 'edit', category: category({ manager_labels: twelvePositions }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await screen.findAllByRole('button', { name: 'Save' })

    expect(screen.getByRole('textbox', { name: 'A.M. 12' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Add level' })).toBeDisabled()
  })
})
