import { beforeAll, describe, expect, it, vi } from 'vitest'
import { cleanup, render, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductCategoryDetailView } from '@/features/product-categories/product-category-detail'
import { categoryWrapper } from '@/features/product-categories/product-category-test-fixtures'
import type { ProductCategoryDetailWithPermissions } from '@/features/product-categories/types'

/**
 * What the detail shows before any row opens (spec 0229 keeps every closed
 * value as the read-only detail rendered it): the attribute lists split by
 * usage context (spec 0061 follow-up), the rule values with the root they are
 * inherited from, the G.A. denominations. The in-place editing is covered by
 * `product-category-detail-inline-edit.test.tsx`.
 *
 * Spec 0062 (MT-3.2) added `ProductCategoryAttributeLayoutSection` to this
 * same view; its own fetch/save wiring is covered by
 * `product-category-attribute-layout-section.test.tsx`, so here its data
 * hook is stubbed. Spec 0141's report-columns catalogue is stubbed the same
 * way.
 */
vi.mock('@/features/product-categories/use-report-columns-catalog', () => ({
  useReportColumnsCatalog: () => ({ data: undefined, isPending: false, isError: false }),
}))

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
    permissions: PERMISSIONS,
    ...overrides,
  }
}

function renderView(detail: ProductCategoryDetailWithPermissions) {
  return render(<ProductCategoryDetailView category={detail} />, { wrapper: categoryWrapper().wrapper })
}

/** The usage-context list whose title is `title` (the title's parent is the list root). */
function contextList(title: string): HTMLElement {
  return screen.getByText(title).parentElement as HTMLElement
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ProductCategoryDetailView — context-scoped attribute sections (spec 0061)', () => {
  it('renders the context title of every context that has an attribute', () => {
    renderView(category({
          attributes: [
            { attribute_id: 1, code: 'color', name: 'Color', type: 'enum', is_required: false, sort_order: 0, context: 'product' },
          ],
        }))

    expect(screen.getByText('Product attributes')).toBeInTheDocument()
  })

  it('shows an own attribute only under its own context section', () => {
    renderView(category({
          attributes: [
            { attribute_id: 1, code: 'color', name: 'Color', type: 'enum', is_required: false, sort_order: 0, context: 'product' },
            { attribute_id: 2, code: 'ram_gb', name: 'RAM (GB)', type: 'integer', is_required: true, sort_order: 0, context: 'quote' },
          ],
        }))

    const productSection = contextList('Product attributes')
    const quoteSection = contextList('Quote attributes')

    expect(within(productSection).getByText('Color')).toBeInTheDocument()
    expect(within(productSection).queryByText('RAM (GB)')).not.toBeInTheDocument()
    expect(within(quoteSection).getByText('RAM (GB)')).toBeInTheDocument()
    expect(within(quoteSection).queryByText('Color')).not.toBeInTheDocument()
  })

  it('shows an inherited attribute only under its own context section, under the "inherited" heading', () => {
    renderView(category({
          inherited_attributes: [
            { attribute_id: 3, code: 'weight_kg', name: 'Weight (kg)', type: 'decimal', is_required: false, context: 'product' },
          ],
        }))

    const productSection = contextList('Product attributes')

    expect(within(productSection).getByText('Inherited from ancestor categories')).toBeInTheDocument()
    expect(within(productSection).getByText('Weight (kg)')).toBeInTheDocument()
    expect(screen.queryByText('Quote attributes')).not.toBeInTheDocument()
  })

  it('renders no context list when the category has no attribute in any context', () => {
    renderView(category())

    expect(screen.queryByText('Product attributes')).not.toBeInTheDocument()
    expect(screen.queryByText('Quote attributes')).not.toBeInTheDocument()
    expect(screen.queryByText('Work order attributes')).not.toBeInTheDocument()
  })

  // Spec 0098: a third, independent context section.
  it('shows an own attribute under its own "Work order attributes" section', () => {
    renderView(category({
          attributes: [
            { attribute_id: 4, code: 'site_access', name: 'Site access', type: 'text', is_required: false, sort_order: 0, context: 'work_order' },
          ],
        }))

    const workOrderSection = contextList('Work order attributes')
    expect(within(workOrderSection).getByText('Site access')).toBeInTheDocument()
    expect(screen.queryByText('Quote attributes')).not.toBeInTheDocument()
  })

  it('marks a required own attribute with the "Required" badge', () => {
    renderView(category({
          attributes: [
            { attribute_id: 2, code: 'ram_gb', name: 'RAM (GB)', type: 'integer', is_required: true, sort_order: 0, context: 'quote' },
          ],
        }))

    const quoteSection = contextList('Quote attributes')
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
    renderView(category())

    expect(screen.getByRole('heading', { name: 'Management rules' })).toBeInTheDocument()
  })

  it('shows the quote flag with no source badge on a root category', () => {
    renderView(category({ requires_quote: true }))

    const field = ruleValue('Quoted')
    expect(within(field).getByText('Yes')).toBeInTheDocument()
    expect(within(field).queryByText(/Inherited from/)).not.toBeInTheDocument()
  })

  it('names the root the quote flag is inherited from on a child category', () => {
    renderView(category({
          requires_quote: true,
          requires_quote_source_category: { id: 1, name: 'Electronics' },
        }))

    const field = ruleValue('Quoted')
    expect(within(field).getByText('Yes')).toBeInTheDocument()
    expect(within(field).getByText('Inherited from Electronics')).toBeInTheDocument()
  })

  it('shows the one-offer rule, with the root it is inherited from', () => {
    renderView(category())
    expect(within(ruleValue('One offer per opportunity')).getByText('No')).toBeInTheDocument()

    cleanup()
    renderView(category({
          single_quote_per_opportunity: true,
          generates_contract: true,
          single_quote_per_opportunity_source_category: { id: 1, name: 'Electronics' },
          generates_contract_source_category: { id: 1, name: 'Electronics' },
        }))

    const field = ruleValue('One offer per opportunity')
    expect(within(field).getByText('Yes')).toBeInTheDocument()
    expect(within(field).getByText('Inherited from Electronics')).toBeInTheDocument()
  })

  // Spec 0114 AC-022.
  it('shows the simplified-offer-line rule with no source badge on a root category', () => {
    renderView(category({ simplified_offer_line: true }))

    const field = ruleValue('Simplified offer line')
    expect(within(field).getByText('Yes')).toBeInTheDocument()
    expect(within(field).queryByText(/Inherited from/)).not.toBeInTheDocument()
  })

  it('names the root the simplified-offer-line rule is inherited from on a child category', () => {
    renderView(category({
          simplified_offer_line: true,
          simplified_offer_line_override: null,
          simplified_offer_line_source_category: { id: 1, name: 'Electronics' },
        }))

    const field = ruleValue('Simplified offer line')
    expect(within(field).getByText('Yes')).toBeInTheDocument()
    expect(within(field).getByText('Inherited from Electronics')).toBeInTheDocument()
  })

  // Spec 0188 AC-014.
  it('shows the effective value of a forced simplified-offer-line with no source badge', () => {
    renderView(category({
          simplified_offer_line: false,
          simplified_offer_line_override: false,
          simplified_offer_line_source_category: null,
        }))

    const field = ruleValue('Simplified offer line')
    expect(within(field).getByText('No')).toBeInTheDocument()
    expect(within(field).queryByText(/Inherited from/)).not.toBeInTheDocument()
  })

  it('names the nearest overriding ancestor as source of an inherited simplified-offer-line', () => {
    renderView(category({
          simplified_offer_line: false,
          simplified_offer_line_override: null,
          simplified_offer_line_source_category: { id: 7, name: 'E-Campus' },
        }))

    const field = ruleValue('Simplified offer line')
    expect(within(field).getByText('No')).toBeInTheDocument()
    expect(within(field).getByText('Inherited from E-Campus')).toBeInTheDocument()
  })

  // Spec 0141 AC-003/AC-006.
  it('omits the report-columns row entirely while the category is not effectively reportable', () => {
    renderView(category())

    expect(screen.queryByText('Report columns')).not.toBeInTheDocument()
  })

  it('shows the own report columns with no source badge', () => {
    renderView(category({
          effective_is_reportable: true,
          report_columns: ['telefonate', 'richiami'],
          effective_report_columns: ['telefonate', 'richiami'],
          report_columns_source_category: null,
        }))

    const field = screen.getByText('Report columns').nextElementSibling as HTMLElement
    expect(within(field).getByText('telefonate')).toBeInTheDocument()
    expect(within(field).getByText('richiami')).toBeInTheDocument()
    expect(within(field).queryByText(/Inherited from/)).not.toBeInTheDocument()
  })

  it('names the ancestor the report columns are inherited from', () => {
    renderView(category({
          effective_is_reportable: true,
          report_columns: null,
          effective_report_columns: ['telefonate'],
          report_columns_source_category: { id: 1, name: 'Electronics' },
        }))

    const field = screen.getByText('Report columns').nextElementSibling as HTMLElement
    expect(within(field).getByText('telefonate')).toBeInTheDocument()
    expect(within(field).getByText('Inherited from Electronics')).toBeInTheDocument()
  })

  it('shows the empty state when no column applies even though the category is reportable', () => {
    renderView(category({ effective_is_reportable: true }))

    expect(screen.getByText('Report columns')).toBeInTheDocument()
  })
})

describe('ProductCategoryDetailView — manager labels (spec 0080)', () => {
  it('shows the empty marker when the category has neither own nor inherited labels', () => {
    renderView(category())

    const section = screen.getByRole('heading', { name: 'Account managers' }).closest('section') as HTMLElement
    expect(within(section).queryByText(/A\.M\./)).not.toBeInTheDocument()
    expect(within(section).getByText('—')).toBeInTheDocument()
  })

  it('shows an own label with no "Inherited" badge', () => {
    renderView(category({ manager_labels: { '1': 'Commercial' } }))

    const section = screen.getByRole('heading', { name: 'Account managers' }).closest('section') as HTMLElement
    expect(within(section).getByText('Commercial')).toBeInTheDocument()
    expect(within(section).queryByText('Inherited')).not.toBeInTheDocument()
  })

  it('shows an inherited label with the "Inherited" badge, own winning over inherited on the same position', () => {
    renderView(category({
          manager_labels: { '1': 'Commercial' },
          inherited_manager_labels: { '1': 'Sales rep', '2': 'Operator' },
        }))

    const section = screen.getByRole('heading', { name: 'Account managers' }).closest('section') as HTMLElement
    // Position 1: own wins, no badge; position 2: inherited only, badge shown.
    expect(within(section).getByText('Commercial')).toBeInTheDocument()
    expect(within(section).queryByText('Sales rep')).not.toBeInTheDocument()
    expect(within(section).getByText('Operator')).toBeInTheDocument()
    expect(within(section).getAllByText('Inherited')).toHaveLength(1)
  })

  // Spec 0080 amendment A1: positions are no longer bounded to 1..4.
  it('shows positions beyond the 4th, sorted ascending (AC-050, AC-053)', () => {
    renderView(category({ manager_labels: { '12': 'Director', '5': 'Regional lead' } }))

    const section = screen.getByRole('heading', { name: 'Account managers' }).closest('section') as HTMLElement
    const rows = within(section).getAllByText(/A\.M\./)
    expect(rows.map((row) => row.textContent)).toEqual(['A.M. 5', 'A.M. 12'])
    expect(within(section).getByText('Regional lead')).toBeInTheDocument()
    expect(within(section).getByText('Director')).toBeInTheDocument()
  })
})
