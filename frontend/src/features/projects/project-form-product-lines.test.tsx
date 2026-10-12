import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { ProjectDetailView } from '@/features/projects/project-detail'
import { ProjectForm } from '@/features/projects/project-form'
import {
  clickCreateSave,
  clickInRow,
  completeRequiredCreateRows,
  pencilOf,
  permissionsFor,
  projectFixture,
  projectWrapper,
  typeInRow,
} from '@/features/projects/project-test-fixtures'
import type { ProjectDetailWithPermissions } from '@/features/projects/types'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'


/**
 * The project's product lines (spec 0094/0132, AC-045) and Sede.
 * REQUIREMENT CHANGED (spec 0220): the row editor opens from the "Product
 * lines" row, on the create draft and on the in-place detail.
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
    is_active: true,
    is_reportable: false,
    management_mode: 'multiple',
    single_quote_per_opportunity: false,
    generates_contract: true,
    simplified_offer_line: false,
    simplified_offer_line_override: null,
    ...overrides,
  }
}

/** The real `ProductLinesField` resolves an edited row's root from the cached tree (spec 0132 AC-017). */
const CATEGORY_TREE: ProductCategoryTreeNode[] = [
  node({ id: 2, name: 'Widgets root', children: [node({ id: 4, name: 'Widgets', parent_id: 2 })] }),
]

vi.mock('@/features/product-categories/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/product-categories/api')>()),
  fetchProductCategoryTree: () => Promise.resolve(CATEGORY_TREE),
}))

const createProjectMock = vi.fn()
const updateProjectMock = vi.fn()
const fetchProjectNextCodeMock = vi.fn<() => Promise<string>>()

vi.mock('@/features/projects/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/projects/api')>()),
  createProject: (...args: unknown[]) => createProjectMock(...args),
  updateProject: (...args: unknown[]) => updateProjectMock(...args),
  fetchProjectNextCode: () => fetchProjectNextCodeMock(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: vi.fn().mockResolvedValue({ items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }),
}))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const fetchSystemStatusIdMock = vi.fn<() => Promise<number | null>>()
vi.mock('@/features/status-reorder/api', () => ({
  fetchSystemStatusId: () => fetchSystemStatusIdMock(),
}))

vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/projects/project-test-stubs')).StubAsyncSelect,
}))
vi.mock('@/features/product-lines/product-category-tree-select', async () => ({
  ProductCategoryTreeSelect: (await import('@/features/projects/project-test-stubs')).StubTreeSelect,
}))
vi.mock('@/features/product-lines/product-category-root-select', async () => ({
  ProductCategoryRootSelect: (await import('@/features/projects/project-test-stubs')).StubRootSelect,
}))
vi.mock('@/features/geo/geo-select', async () => ({
  GeoSelect: (await import('@/features/projects/project-test-stubs')).StubGeoSelect,
}))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

function renderCreate(source?: ProjectDetailWithPermissions) {
  const { client, wrapper } = projectWrapper()
  render(<ProjectForm source={source} onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper })
  return client
}

function renderDetail(project: ProjectDetailWithPermissions) {
  const { client, wrapper } = projectWrapper()
  render(<ProjectDetailView project={project} />, { wrapper })
  return client
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.restoreAllMocks()
  createProjectMock.mockReset()
  updateProjectMock.mockReset()
  fetchProjectNextCodeMock.mockReset()
  fetchProjectNextCodeMock.mockResolvedValue('PRJ-0100')
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
  fetchSystemStatusIdMock.mockReset()
  fetchSystemStatusIdMock.mockResolvedValue(null)
})

describe('Project — product_lines (spec 0132, AC-045)', () => {
  it('opens the create rows on ONE empty line, its category disabled until the root is chosen', async () => {
    renderCreate()

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Product lines' }))
    expect(screen.queryByTestId('select-Parent category 2')).not.toBeInTheDocument()
    expect(screen.getByTestId('select-Product category 1')).toBeDisabled()

    fireEvent.click(screen.getByTestId('select-Parent category 1'))
    await waitFor(() => expect(screen.getByTestId('select-Product category 1')).toBeEnabled())
  })

  it('leaves the row category enabled on the detail (its root resolves from the tree)', async () => {
    renderDetail(projectFixture())

    fireEvent.click(pencilOf('Product lines'))
    await waitFor(() => expect(screen.getByTestId('select-Product category 1')).toBeEnabled())
  })

  it('adds a second independent row via "Add", each scoped by its OWN root category', async () => {
    renderCreate()

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Product lines' }))
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))

    expect(screen.getByTestId('select-Parent category 2')).toBeInTheDocument()
    expect(screen.getByTestId('disabled-Product category 2')).toHaveTextContent('true')
  })

  it('refuses the save while the collection is incomplete (AC-045)', async () => {
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    typeInRow('Name', 'Acme rollout')
    clickInRow('Status', 'select-Status')
    clickInRow('Geography', 'geo-select')
    typeInRow('Start date', '2026-01-01')
    // The default row is left incomplete on purpose (no category picked).
    clickCreateSave()

    expect(await screen.findByText('Each row requires both a parent category and a product category.')).toBeInTheDocument()
    expect(createProjectMock).not.toHaveBeenCalled()
  })

  it('sends every complete row on create', async () => {
    createProjectMock.mockResolvedValue(projectFixture())
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    completeRequiredCreateRows()
    clickCreateSave()

    await waitFor(() => expect(createProjectMock).toHaveBeenCalledTimes(1))
    // Spec 0132 AC-018: only `product_category_id` travels — the picked root is UI-only state.
    expect(createProjectMock.mock.calls[0][0].product_lines).toEqual([{ product_category_id: 4 }])
  })
})

describe('Project — Sede (operational site)', () => {
  it('opens the Site row of the detail on the persisted Sede', () => {
    renderDetail(projectFixture({ operational_site_id: 8, operational_site: { id: 8, label: 'Warehouse A' } }))

    expect(screen.getByText('Warehouse A')).toBeInTheDocument()
    fireEvent.click(pencilOf('Site'))
    expect(screen.getByTestId('select-Site')).toHaveTextContent('8')
  })

  it('sends the picked operational_site_id in the create payload', async () => {
    createProjectMock.mockResolvedValue(projectFixture())
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    completeRequiredCreateRows()
    clickInRow('Site', 'select-Site')
    clickCreateSave()

    await waitFor(() => expect(createProjectMock).toHaveBeenCalledTimes(1))
    expect(createProjectMock.mock.calls[0][0].operational_site_id).toBe(3)
  })
})
