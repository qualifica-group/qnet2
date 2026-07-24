import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'
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
    inherits_attributes: true,
    description: null,
    attributes: [],
    inherited_attributes: [],
    created_at: '2026-01-01T00:00:00Z',
    business_function_id: null,
    business_function: null,
    effective_business_function: null,
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
            { attribute_id: 2, code: 'ram_gb', name: 'RAM (GB)', type: 'integer', is_required: true, sort_order: 0, context: 'opportunity' },
          ],
        })}
      />,
    )

    const productSection = screen.getByRole('heading', { name: 'Product attributes' }).closest('section') as HTMLElement
    const opportunitySection = screen
      .getByRole('heading', { name: 'Opportunity attributes' })
      .closest('section') as HTMLElement

    expect(within(productSection).getByText('Color')).toBeInTheDocument()
    expect(within(productSection).queryByText('RAM (GB)')).not.toBeInTheDocument()
    expect(within(opportunitySection).getByText('RAM (GB)')).toBeInTheDocument()
    expect(within(opportunitySection).queryByText('Color')).not.toBeInTheDocument()
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
    expect(screen.queryByRole('heading', { name: 'Opportunity attributes' })).not.toBeInTheDocument()
  })

  it('renders neither section when the category has no attribute in either context', () => {
    render(<ProductCategoryDetailView category={category()} />)

    expect(screen.queryByRole('heading', { name: 'Product attributes' })).not.toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Opportunity attributes' })).not.toBeInTheDocument()
  })

  it('marks a required own attribute with the "Required" badge', () => {
    render(
      <ProductCategoryDetailView
        category={category({
          attributes: [
            { attribute_id: 2, code: 'ram_gb', name: 'RAM (GB)', type: 'integer', is_required: true, sort_order: 0, context: 'opportunity' },
          ],
        })}
      />,
    )

    const opportunitySection = screen
      .getByRole('heading', { name: 'Opportunity attributes' })
      .closest('section') as HTMLElement
    expect(within(opportunitySection).getByText('Required')).toBeInTheDocument()
  })
})
