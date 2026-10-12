import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductCategoryDetailView } from '@/features/product-categories/product-category-detail'
import { categoryWrapper, pencilOf, permissionsFor } from '@/features/product-categories/product-category-test-fixtures'
import type { ProductCategoryDetailWithPermissions } from '@/features/product-categories/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * Spec 0141 AC-009 (rev-1): the report-columns picker inside "Regole di
 * gestione". `useReportColumnsInheritance` reads `inherited_report_columns` /
 * `inherited_report_columns_source_category` straight off the loaded
 * category detail — resolved from the ANCESTRY ALONE, independent of the
 * category's own value — so unlike the `requires_quote` suite this one never
 * needs to mock the category tree beyond the empty list the parent picker
 * itself fetches. rev-1's own fix: "Back to inherited" must show ONLY while
 * an ancestor actually configures something (`inherited_report_columns` not
 * empty) — a category that is the configuration's own ORIGIN never offers it.
 */

const createProductCategoryMock = vi.fn()
const updateProductCategoryMock = vi.fn()
const fetchReportColumnsCatalogMock = vi.fn()

vi.mock('@/features/product-categories/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/product-categories/api')>()),
  createProductCategory: (...args: unknown[]) => createProductCategoryMock(...args),
  updateProductCategory: (...args: unknown[]) => updateProductCategoryMock(...args),
  fetchProductCategoryTree: () => Promise.resolve([]),
  fetchEffectiveAttributes: () => Promise.resolve([]),
  fetchReportColumnsCatalog: () => fetchReportColumnsCatalogMock(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/features/for-select/use-for-select', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/use-for-select')>(
    '@/features/for-select/use-for-select',
  )
  return {
    flattenForSelectPages: actual.flattenForSelectPages,
    useForSelect: () => ({
      data: { pages: [{ items: [] }] },
      isPending: false,
      isError: false,
      fetchNextPage: vi.fn(),
      hasNextPage: false,
      isFetchingNextPage: false,
      refetch: vi.fn(),
    }),
    useForSelectLabels: () => new Map(),
  }
})

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

function wrapper() {
  return categoryWrapper().wrapper
}

function renderDetail(detail: ProductCategoryDetailWithPermissions) {
  return render(<ProductCategoryDetailView category={detail} />, { wrapper: wrapper() })
}

/** The report columns row, opened on its picker (the pencil of the row). */
function renderOpen(detail: ProductCategoryDetailWithPermissions) {
  const view = renderDetail(detail)
  fireEvent.click(pencilOf('reportColumns') as HTMLElement)
  return view
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
    business_function: null,
    effective_business_function: null,
    requires_quote: false,
    requires_quote_source_category: null,
    is_selectable: true,
    is_active: true,
    is_reportable: true,
    effective_is_reportable: true,
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
    permissions: permissionsFor(),
    ...overrides,
  }
}

const CATALOG = [
  { key: 'phone_calls', label: 'Phone calls' },
  { key: 'callbacks', label: 'Callbacks' },
  { key: 'new_contacts', label: 'New contacts' },
]

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createProductCategoryMock.mockReset()
  updateProductCategoryMock.mockReset()
  fetchReportColumnsCatalogMock.mockReset().mockResolvedValue(CATALOG)
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
})

describe('report columns row (spec 0141, spec 0229)', () => {
  it('is not rendered while the category is not effectively reportable', async () => {
    renderDetail(category({ is_reportable: false, effective_is_reportable: false }))

    expect(pencilOf('reportColumns')).not.toBeInTheDocument()
    expect(screen.queryByText('Report columns')).not.toBeInTheDocument()
  })

  it('shows the inherited columns, checked, with the source category named', async () => {
    renderOpen(category({
            parent_id: 1,
            parent: { id: 1, name: 'Electronics' },
            report_columns: null,
            effective_report_columns: ['phone_calls'],
            report_columns_source_category: { id: 1, name: 'Electronics' },
            inherited_report_columns: ['phone_calls'],
            inherited_report_columns_source_category: { id: 1, name: 'Electronics' },
          }))

    expect(await screen.findByRole('checkbox', { name: 'Phone calls' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Callbacks' })).not.toBeChecked()
    expect(screen.getByText('Inherited from "Electronics".')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Back to inherited' })).not.toBeInTheDocument()
  })

  it('no ancestor configures anything: unchecked, with the "no source" hint', async () => {
    renderOpen(category())

    expect(await screen.findByRole('checkbox', { name: 'Phone calls' })).not.toBeChecked()
    expect(
      screen.getByText(
        'No ancestor category has columns configured. Pick some to include this category in the report and dashboard.',
      ),
    ).toBeInTheDocument()
  })

  it('checking a catalogue chip under a configured ancestor overrides it, and the reset action appears', async () => {
    renderOpen(category({
            parent_id: 1,
            parent: { id: 1, name: 'Electronics' },
            report_columns: null,
            effective_report_columns: ['phone_calls'],
            report_columns_source_category: { id: 1, name: 'Electronics' },
            inherited_report_columns: ['phone_calls'],
            inherited_report_columns_source_category: { id: 1, name: 'Electronics' },
          }))

    fireEvent.click(await screen.findByRole('checkbox', { name: 'Callbacks' }))

    expect(screen.getByRole('checkbox', { name: 'Phone calls' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Callbacks' })).toBeChecked()
    expect(screen.getByText('Overrides the columns inherited from "Electronics".')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Back to inherited' })).toBeInTheDocument()
  })

  // Bug fixed by rev-1: a category with own columns but NO ancestor
  // configured (the configuration's own origin, e.g. GOL under a plain
  // Formazione) must never offer a reset that has nothing to fall back to.
  it('origin category (own columns, no configured ancestor): never shows the reset action', async () => {
    renderOpen(category({
            report_columns: ['phone_calls'],
            effective_report_columns: ['phone_calls'],
            report_columns_source_category: null,
            inherited_report_columns: [],
            inherited_report_columns_source_category: null,
          }))

    expect(await screen.findByRole('checkbox', { name: 'Phone calls' })).toBeChecked()
    expect(screen.getByText('Set on this category: every subcategory inherits it.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Back to inherited' })).not.toBeInTheDocument()
  })

  it('origin category: unchecking every own column goes back to inheriting nothing (AC-009 "deselezionare tutte = null")', async () => {
    renderOpen(category({
            report_columns: ['phone_calls'],
            effective_report_columns: ['phone_calls'],
            report_columns_source_category: null,
            inherited_report_columns: [],
            inherited_report_columns_source_category: null,
          }))

    const phoneCalls = await screen.findByRole('checkbox', { name: 'Phone calls' })
    expect(phoneCalls).toBeChecked()

    fireEvent.click(phoneCalls)

    await waitFor(() => expect(phoneCalls).not.toBeChecked())
    expect(screen.queryByRole('button', { name: 'Back to inherited' })).not.toBeInTheDocument()
  })

  it('child under a configured ancestor: "Back to inherited" resets to null and shows the ancestor columns', async () => {
    renderOpen(category({
            parent_id: 1,
            parent: { id: 1, name: 'Electronics' },
            report_columns: ['phone_calls', 'new_contacts'],
            effective_report_columns: ['phone_calls', 'new_contacts'],
            report_columns_source_category: null,
            inherited_report_columns: ['phone_calls'],
            inherited_report_columns_source_category: { id: 1, name: 'Electronics' },
          }))

    fireEvent.click(await screen.findByRole('button', { name: 'Back to inherited' }))

    // Reverted to null: the picker now shows what Electronics configures.
    await waitFor(() => expect(screen.getByRole('checkbox', { name: 'Phone calls' })).toBeChecked())
    expect(screen.getByRole('checkbox', { name: 'New contacts' })).not.toBeChecked()
    expect(screen.getByText('Inherited from "Electronics".')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Back to inherited' })).not.toBeInTheDocument()
  })

  it('sends the own selection, catalog-ordered, on save', async () => {
    const saved = category({ report_columns: ['phone_calls', 'callbacks'] })
    updateProductCategoryMock.mockResolvedValue(saved)

    renderOpen(category())

    // Catalog order is phone_calls, callbacks, new_contacts: clicking the
    // last two first still sends them in catalog order.
    fireEvent.click(await screen.findByRole('checkbox', { name: 'Callbacks' }))
    fireEvent.click(screen.getByRole('checkbox', { name: 'Phone calls' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateProductCategoryMock.mock.calls[0]
    expect(payload).toEqual({ report_columns: ['phone_calls', 'callbacks'] })
  })

  it('the grid is an accessible group named by the card title, with the counter and status chips', async () => {
    renderOpen(category({
            parent_id: 1,
            parent: { id: 1, name: 'Electronics' },
            report_columns: null,
            effective_report_columns: ['phone_calls'],
            report_columns_source_category: { id: 1, name: 'Electronics' },
            inherited_report_columns: ['phone_calls'],
            inherited_report_columns_source_category: { id: 1, name: 'Electronics' },
          }))

    expect(await screen.findByRole('group', { name: 'Report columns' })).toBeInTheDocument()
    expect(screen.getByText('1/3')).toBeInTheDocument()
    expect(screen.getByText('Inherited from Electronics')).toBeInTheDocument()
  })

  it('"Own" chip and counter when the category has its own selection', async () => {
    renderOpen(category({ report_columns: ['phone_calls', 'new_contacts'] }))

    await screen.findByRole('group', { name: 'Report columns' })
    expect(screen.getByText('2/3')).toBeInTheDocument()
    expect(screen.getByText('Own')).toBeInTheDocument()
  })

  it('"No columns" chip when nothing is own or inherited', async () => {
    renderOpen(category())

    await screen.findByRole('group', { name: 'Report columns' })
    expect(screen.getByText('0/3')).toBeInTheDocument()
    expect(screen.getByText('No columns')).toBeInTheDocument()
  })

  it('"All" selects every catalogue key and then disables itself', async () => {
    renderOpen(category())

    fireEvent.click(await screen.findByRole('button', { name: 'All' }))

    expect(screen.getByRole('checkbox', { name: 'Phone calls' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Callbacks' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'New contacts' })).toBeChecked()
    expect(screen.getByText('3/3')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'All' })).toBeDisabled()
  })

  it('"None" clears an origin category\'s own selection (no "Back to inherited" alongside it)', async () => {
    renderOpen(category({ report_columns: ['phone_calls'] }))

    expect(await screen.findByRole('checkbox', { name: 'Phone calls' })).toBeChecked()
    expect(screen.queryByRole('button', { name: 'Back to inherited' })).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'None' }))

    await waitFor(() => expect(screen.getByRole('checkbox', { name: 'Phone calls' })).not.toBeChecked())
    expect(screen.queryByRole('button', { name: 'None' })).not.toBeInTheDocument()
  })

  it('shows a skeleton while the catalogue loads, then the grid', async () => {
    let resolveCatalog: (value: typeof CATALOG) => void = () => {}
    fetchReportColumnsCatalogMock.mockReset().mockReturnValue(
      new Promise((resolve) => {
        resolveCatalog = resolve
      }),
    )

    renderOpen(category())

    await screen.findAllByText('Report columns')
    expect(screen.queryByRole('group')).not.toBeInTheDocument()

    resolveCatalog(CATALOG)

    expect(await screen.findByRole('group', { name: 'Report columns' })).toBeInTheDocument()
  })

  it('shows a retryable error when the catalogue fails to load', async () => {
    fetchReportColumnsCatalogMock.mockReset().mockRejectedValueOnce(new Error('network'))

    renderOpen(category())

    expect(await screen.findByText('Unable to load the column catalogue. Try again.')).toBeInTheDocument()

    fetchReportColumnsCatalogMock.mockResolvedValue(CATALOG)
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))

    expect(await screen.findByRole('group', { name: 'Report columns' })).toBeInTheDocument()
  })
})
