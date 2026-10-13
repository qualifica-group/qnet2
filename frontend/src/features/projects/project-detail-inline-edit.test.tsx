import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { ProjectDetailView } from '@/features/projects/project-detail'
import {
  READ_ONLY,
  clickInRow,
  inputOf,
  pencilOf,
  permissionsFor,
  projectFixture,
  projectWrapper,
  typeInRow,
} from '@/features/projects/project-test-fixtures'
import type { ProjectDetailWithPermissions } from '@/features/projects/types'

/**
 * Spec 0220: the project detail edits in place, one row at a time. Each save
 * is a PATCH of that field alone — the geography row sends the levels of its
 * cascade that changed.
 */

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

describe('ProjectDetailView — in-place editing (spec 0220)', () => {
  it('PATCHes the edited field alone and seeds the cache with the permissions', async () => {
    const saved = projectFixture({ total_budget: '5000.00' })
    updateProjectMock.mockResolvedValue(saved)
    const client = renderDetail(projectFixture())

    typeInRow('Total budget', '5000', 'Save')

    await waitFor(() => expect(updateProjectMock).toHaveBeenCalledWith(7, { total_budget: 5000 }))
    expect(client.getQueryData<ProjectDetailWithPermissions>(['projects', 'detail', 7])?.permissions).toEqual(
      saved.permissions,
    )
  })

  it('sends the geography cascade as one change', async () => {
    updateProjectMock.mockResolvedValue(projectFixture())
    renderDetail(projectFixture({ country_id: 9, country: { id: 9, name: 'France' }, geo_scope: 'country' }))

    clickInRow('Geography', 'geo-select', 'Save')

    await waitFor(() => expect(updateProjectMock).toHaveBeenCalledWith(7, { country_id: 1 }))
  })

  it('closes on Cancel without calling the server', () => {
    renderDetail(projectFixture())

    fireEvent.click(pencilOf('Name'))
    fireEvent.change(inputOf('Name'), { target: { value: 'Changed' } })
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(screen.getAllByText('Acme rollout').length).toBeGreaterThan(0)
    expect(updateProjectMock).not.toHaveBeenCalled()
  })

  it('offers no pencil on a field the actor cannot edit', () => {
    renderDetail(projectFixture({ permissions: permissionsFor({ code: READ_ONLY, name: READ_ONLY }) }))

    expect(screen.queryByRole('button', { name: 'Edit Name' })).not.toBeInTheDocument()
    expect(pencilOf('Description')).toBeInTheDocument()
  })

  it('offers no Edit action: the record edits in place (spec 0220 AC-001)', () => {
    renderDetail(projectFixture())

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
  })
})
