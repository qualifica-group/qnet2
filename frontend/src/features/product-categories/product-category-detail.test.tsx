import { beforeAll, describe, expect, it, vi } from 'vitest'
import { cleanup, render, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductCategoryDetailView } from '@/features/product-categories/product-category-detail'
import type { ProductCategoryDetailWithPermissions } from '@/features/product-categories/types'

/**
 * Spec 0061 follow-up: the read-only detail view splits `attributes` +
 * `inherited_attributes` into the SAME two context-scoped sections the
 * editor uses, instead of one flat, context-unlabeled list.
 *
 * Spec 0062 (MT-3.2) added `ProductCategoryAttributeLayoutSection` to this
 * same view; its own fetch/save wiring is covered by
 * `product-category-attribute-layout-section.test.tsx`, so here its data
 * hook is stubbed to keep this suite focused on the attribute sections above
 * it (and QueryClient-free, since the stub replaces the only `useQuery` call
 * in the tree).
 */
vi.mock('@/features/product-categories/use-attribute-layout', () => ({
  useAttributeLayout: () => ({
    attributes: [],
    draft: { sections: [] },
    setDraft: vi.fn(),
    isLoading: false,
    isError: false,
    refetch: vi.fn(),
    isSaving: false,
    error: null,
    save: vi.fn(async () => true),
  }),
}))

const PERMISSIONS: ProductCategoryDetailWithPermissions['permissions'] = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: { view_activity: false },
}

function category(
  overrides: Partial<ProductCategoryDetailWithPermissions> = {},
): ProductCategoryDetailWithPermissions {
  return {
    id: 4,
    name: 'Laptops',
    parent_id: 1,
    parent: { id: 1, name: 'Electronics' },
    inherits_product_attributes: true,
    inherits_quote_attributes: true,
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
    manager_labels: {},
    inherits_manager_labels: true,
    inherited_manager_labels: {},
    permissions: PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ProductCategoryDetailView — context-scoped attribute sections (spec 0061)', () => {
  it('renders both section titles with their description', () => {
    render(
      <ProductCategoryDetailView
        category={category({
          attributes: [
            { attribute_id: 1, code: 'color', name: 'Color', type: 'enum', is_required: false, sort_order: 0, context: 'product' },
          ],
        })}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Product attributes' })).toBeInTheDocument()
    expect(
      screen.getByText('Loaded in the Product card (create/edit) for products in this category.'),
    ).toBeInTheDocument()
  })

  it('shows an own attribute only under its own context section', () => {
    render(
      <ProductCategoryDetailView
        category={category({
          attributes: [
            { attribute_id: 1, code: 'color', name: 'Color', type: 'enum', is_required: false, sort_order: 0, context: 'product' },
            { attribute_id: 2, code: 'ram_gb', name: 'RAM (GB)', type: 'integer', is_required: true, sort_order: 0, context: 'quote' },
          ],
        })}
      />,
    )

    const productSection = screen.getByRole('heading', { name: 'Product attributes' }).closest('section') as HTMLElement
    const quoteSection = screen
      .getByRole('heading', { name: 'Quote attributes' })
      .closest('section') as HTMLElement

    expect(within(productSection).getByText('Color')).toBeInTheDocument()
    expect(within(productSection).queryByText('RAM (GB)')).not.toBeInTheDocument()
    expect(within(quoteSection).getByText('RAM (GB)')).toBeInTheDocument()
    expect(within(quoteSection).queryByText('Color')).not.toBeInTheDocument()
  })

  it('shows an inherited attribute only under its own context section, under the "inherited" heading', () => {
    render(
      <ProductCategoryDetailView
        category={category({
          inherited_attributes: [
            { attribute_id: 3, code: 'weight_kg', name: 'Weight (kg)', type: 'decimal', is_required: false, context: 'product' },
          ],
        })}
      />,
    )

    const productSection = screen.getByRole('heading', { name: 'Product attributes' }).closest('section') as HTMLElement

    expect(within(productSection).getByText('Inherited from ancestor categories')).toBeInTheDocument()
    expect(within(productSection).getByText('Weight (kg)')).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Quote attributes' })).not.toBeInTheDocument()
  })

  it('renders neither section when the category has no attribute in either context', () => {
    render(<ProductCategoryDetailView category={category()} />)

    expect(screen.queryByRole('heading', { name: 'Product attributes' })).not.toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Quote attributes' })).not.toBeInTheDocument()
  })

  it('marks a required own attribute with the "Required" badge', () => {
    render(
      <ProductCategoryDetailView
        category={category({
          attributes: [
            { attribute_id: 2, code: 'ram_gb', name: 'RAM (GB)', type: 'integer', is_required: true, sort_order: 0, context: 'quote' },
          ],
        })}
      />,
    )

    const quoteSection = screen
      .getByRole('heading', { name: 'Quote attributes' })
      .closest('section') as HTMLElement
    expect(within(quoteSection).getByText('Required')).toBeInTheDocument()
  })
})

/**
 * The four behavioural rules now read as ONE "Management rules" section
 * (user directive 2026-08-07), so a rule is located by its own label rather
 * than by a section heading of its own.
 */
function ruleValue(label: string): HTMLElement {
  return screen.getByText(label).nextElementSibling as HTMLElement
}

describe('ProductCategoryDetailView — management rules', () => {
  it('groups every rule under one section', () => {
    render(<ProductCategoryDetailView category={category()} />)

    expect(screen.getByRole('heading', { name: 'Management rules' })).toBeInTheDocument()
  })

  it('shows the quote flag with no source badge on a root category', () => {
    render(<ProductCategoryDetailView category={category({ requires_quote: true })} />)

    const field = ruleValue('Quoted')
    expect(within(field).getByText('Yes')).toBeInTheDocument()
    expect(within(field).queryByText(/Inherited from/)).not.toBeInTheDocument()
  })

  it('names the root the quote flag is inherited from on a child category', () => {
    render(
      <ProductCategoryDetailView
        category={category({
          requires_quote: true,
          requires_quote_source_category: { id: 1, name: 'Electronics' },
        })}
      />,
    )

    const field = ruleValue('Quoted')
    expect(within(field).getByText('Yes')).toBeInTheDocument()
    expect(within(field).getByText('Inherited from Electronics')).toBeInTheDocument()
  })

  it('shows the one-offer rule, with the root it is inherited from', () => {
    render(<ProductCategoryDetailView category={category()} />)
    expect(within(ruleValue('One offer per opportunity')).getByText('No')).toBeInTheDocument()

    cleanup()
    render(
      <ProductCategoryDetailView
        category={category({
          single_quote_per_opportunity: true,
          generates_contract: true,
          single_quote_per_opportunity_source_category: { id: 1, name: 'Electronics' },
          generates_contract_source_category: { id: 1, name: 'Electronics' },
        })}
      />,
    )

    const field = ruleValue('One offer per opportunity')
    expect(within(field).getByText('Yes')).toBeInTheDocument()
    expect(within(field).getByText('Inherited from Electronics')).toBeInTheDocument()
  })
})

describe('ProductCategoryDetailView — manager labels (spec 0080)', () => {
  it('renders nothing when the category has neither own nor inherited labels', () => {
    render(<ProductCategoryDetailView category={category()} />)

    expect(screen.queryByRole('heading', { name: 'Account managers' })).not.toBeInTheDocument()
  })

  it('shows an own label with no "Inherited" badge', () => {
    render(<ProductCategoryDetailView category={category({ manager_labels: { '1': 'Commercial' } })} />)

    const section = screen.getByRole('heading', { name: 'Account managers' }).closest('section') as HTMLElement
    expect(within(section).getByText('Commercial')).toBeInTheDocument()
    expect(within(section).queryByText('Inherited')).not.toBeInTheDocument()
  })

  it('shows an inherited label with the "Inherited" badge, own winning over inherited on the same position', () => {
    render(
      <ProductCategoryDetailView
        category={category({
          manager_labels: { '1': 'Commercial' },
          inherited_manager_labels: { '1': 'Sales rep', '2': 'Operator' },
        })}
      />,
    )

    const section = screen.getByRole('heading', { name: 'Account managers' }).closest('section') as HTMLElement
    // Position 1: own wins, no badge; position 2: inherited only, badge shown.
    expect(within(section).getByText('Commercial')).toBeInTheDocument()
    expect(within(section).queryByText('Sales rep')).not.toBeInTheDocument()
    expect(within(section).getByText('Operator')).toBeInTheDocument()
    expect(within(section).getAllByText('Inherited')).toHaveLength(1)
  })

  // Spec 0080 amendment A1: positions are no longer bounded to 1..4.
  it('shows positions beyond the 4th, sorted ascending (AC-050, AC-053)', () => {
    render(<ProductCategoryDetailView category={category({ manager_labels: { '12': 'Director', '5': 'Regional lead' } })} />)

    const section = screen.getByRole('heading', { name: 'Account managers' }).closest('section') as HTMLElement
    const rows = within(section).getAllByText(/A\.M\./)
    expect(rows.map((row) => row.textContent)).toEqual(['A.M. 5', 'A.M. 12'])
    expect(within(section).getByText('Regional lead')).toBeInTheDocument()
    expect(within(section).getByText('Director')).toBeInTheDocument()
  })
})
