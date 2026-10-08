import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { OpportunityForm } from '@/features/opportunities/opportunity-form'
import type { ResourceMeta } from '@/features/authorization/types'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import {
  FULL_PERMISSIONS,
  ROW,
  SELECT_IDS,
  formTestWrapper,
  openRow,
  queryPencil,
  resolveForSelectLabels,
} from '@/features/opportunities/opportunity-form-test-helpers'

/**
 * AC-075 (spec 0040 MT-6): the `?lead_id=N` deep-link create-from-lead mode —
 * locked fields precompiled, origin banner shown, free fields stay editable,
 * the derived product-line row seeds already editable/removable. Split out of
 * `opportunity-form-body.test.tsx` for file size (engineering.md §6); the
 * in-form "Lead" select (AC-086/087) is covered in
 * `opportunity-lead-selection.test.tsx`.
 *
 * Spec 0198: the locked fields (BR-2) have NO pencil on their closed row
 * instead of a disabled control.
 */

/**
 * The row's category picker reads the category TREE (user directive
 * 2026-08-03) and mounts its own quick-create affordance; this suite is about
 * the surrounding form, so it stands in for the picker with the shared double.
 */
vi.mock('@/features/product-lines/product-category-tree-select', async () =>
  await import('@/features/product-lines/product-category-tree-select-stub'))

/**
 * `useProductLinesField` (inside the real, unmocked `ProductLinesField`)
 * reads the category tree directly — for `rootCategoryFor`'s resolution of
 * the lead-derived row (spec 0132 AC-017 applies to this prefill too, the
 * category-select must not stay stuck disabled), the tree fetch needs a real
 * answer here, unlike the two row pickers themselves (mocked with doubles).
 */
function node(overrides: Partial<ProductCategoryTreeNode> & { id: number }): ProductCategoryTreeNode {
  return {
    name: 'Node',
    parent_id: null,
    children: [],
    attributes_count: 0,
    products_count: 0,
    business_function_id: null,
    requires_quote: false,
    is_selectable: true,
    is_reportable: false,
    management_mode: 'multiple',
    single_quote_per_opportunity: false,
    generates_contract: true,
    simplified_offer_line: false,
    simplified_offer_line_override: null,
    ...overrides,
  }
}

const CATEGORY_TREE: ProductCategoryTreeNode[] = [
  node({ id: 20, name: 'Sales root', children: [node({ id: 50, name: 'Consulting', parent_id: 20 })] }),
]

vi.mock('@/features/product-categories/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/product-categories/api')>(
    '@/features/product-categories/api',
  )
  return {
    ...actual,
    fetchProductCategoryTree: () => Promise.resolve(CATEGORY_TREE),
  }
})

vi.mock('@/features/product-lines/product-category-root-select', async () => ({
  ProductCategoryRootSelect: (await import('@/features/opportunities/opportunity-form-test-helpers'))
    .ProductCategoryRootSelectDouble,
}))

// Closed rows link their related records (`RecordLink`): the open-mode
// preference and the abilities are stubbed rather than dragging an AuthProvider
// in; every ability is granted, so the links render.
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/opportunities/opportunity-form-test-helpers'))
    .AsyncPaginatedSelectDouble,
}))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return { ...actual, fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args) }
})

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  for (const key of Object.keys(SELECT_IDS)) {
    delete SELECT_IDS[key]
  }
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation(async (resource: string, params: { ids?: number[] }) =>
    resolveForSelectLabels(resource, params),
  )
})

/** AC-075: create-from-lead mode (spec 0040 MT-6) — locked fields precompiled, banner shown, free fields stay editable. */
describe('OpportunityFormBody — create from lead (BR-1/BR-2, AC-075)', () => {
  it('shows the origin banner, locks the derived fields precompiled, and seeds the editable product-line row', async () => {
    render(
      <OpportunityForm
        mode={{
          type: 'create',
          fromLead: {
            leadId: 9,
            values: {
              // spec 0041 D-3/AC-050: no longer a derived field.
              referent_id: null,
              source_id: 20,
              registry_id: 30,
              operational_site_id: null,
              general_notes: null,
            },
            references: {
              source: { id: 20, name: 'Web' },
              registry: { id: 30, name: 'Acme S.p.A.' },
              operational_site: null,
            },
            lockedFields: ['registry_id', 'source_id'],
            productLines: [
              {
                id: 900,
                business_function: { id: 40, name: 'Sales' },
                product_category: { id: 50, name: 'Consulting' },
              },
            ],
            // Directive 2026-07-21: no Operator on this fixture's lead — the
            // first "Gestore Account" slot stays empty (not under test here).
            managerSlots: [],
          },
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: formTestWrapper() },
    )

    await waitFor(() => expect(queryPencil(ROW.title)).toBeInTheDocument())
    // AC-051: the origin banner sources its name from the registry, not the referent.
    expect(screen.getByRole('status')).toHaveTextContent('Acme S.p.A.')

    // REQUIREMENT CHANGED (spec 0198 D-4, BR-2): the locked fields show what
    // the lead handed down on a closed row with NO pencil — no longer a
    // precompiled disabled control.
    expect(screen.getByRole('link', { name: /Acme S\.p\.A\./ })).toBeInTheDocument()
    expect(queryPencil(ROW.registry)).not.toBeInTheDocument()
    expect(await screen.findByText('Web')).toBeInTheDocument()
    expect(queryPencil(ROW.source)).not.toBeInTheDocument()

    // AC-051: referent_id is no longer derived/locked by the lead — free and
    // editable, gated only by the registry now being chosen (it is).
    openRow(ROW.referent)
    expect(screen.getByTestId('value-Contact')).toHaveTextContent('')
    expect(screen.getByTestId('disabled-Contact')).toHaveTextContent('false')

    // Spec 0132: the seeded row is editable/removable — never locked. Only
    // `product_category_id` is known from the lead's derived line; the root
    // is resolved from the cached tree at render time, so the category select
    // only unlocks once that fetch settles. The closed row names its path.
    expect(await screen.findByText('Sales root > Consulting')).toBeInTheDocument()
    openRow(ROW.productLines)
    expect(screen.getByTestId('value-Product category 1')).toHaveTextContent('50')
    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))
    expect(screen.getByTestId('disabled-Parent category 1')).toHaveTextContent('false')
    expect(screen.getByRole('button', { name: 'Remove product line' })).toBeInTheDocument()
  })

  it('renders no banner and no locked field for a plain manual create', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: formTestWrapper(),
    })

    await waitFor(() => expect(queryPencil(ROW.title)).toBeInTheDocument())
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
    expect(queryPencil(ROW.registry)).toBeInTheDocument()
    expect(queryPencil(ROW.source)).toBeInTheDocument()
    openRow(ROW.registry)
    expect(screen.getByTestId('disabled-Registry')).toHaveTextContent('false')
  })
})
