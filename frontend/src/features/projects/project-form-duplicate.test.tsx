import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { ProjectForm } from '@/features/projects/project-form'
import {
  clickCreateSave,
  confirmOpenRow,
  inputOf,
  pencilOf,
  permissionsFor,
  projectFixture,
  projectWrapper,
} from '@/features/projects/project-test-fixtures'
import type { ProjectDetailWithPermissions } from '@/features/projects/types'


/**
 * Row action "duplicate": the create form prefilled from a source project —
 * a fresh code, the copy suffix on the name, every other field carried over —
 * submitted through the create API. REQUIREMENT CHANGED (spec 0220): the
 * carried-over values are read on the closed rows.
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

function renderCreate(source?: ProjectDetailWithPermissions) {
  const { client, wrapper } = projectWrapper()
  render(<ProjectForm source={source} onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper })
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

describe('Project — duplicate (row action "duplicate")', () => {
  it('seeds the name with the copy suffix, a fresh code, and submits via the create API', async () => {
    fetchProjectNextCodeMock.mockResolvedValue('PRJ-0200')
    createProjectMock.mockResolvedValue(projectFixture({ id: 99, code: 'PRJ-0200', name: 'Acme rollout (copy)' }))
    renderCreate(projectFixture({ name: 'Acme rollout' }))

    expect((await screen.findAllByText('Acme rollout (copy)')).length).toBeGreaterThan(0)
    fireEvent.click(pencilOf('Code'))
    // The source's own code (PRJ-0007) is discarded in favor of a fresh sequential suggestion.
    expect(inputOf('Code')).toHaveValue('PRJ-0200')
    confirmOpenRow('Done')
    fireEvent.click(pencilOf('Status'))
    expect(screen.getByTestId('select-Status')).toHaveTextContent('3')
    confirmOpenRow('Done')

    clickCreateSave()

    await waitFor(() => expect(createProjectMock).toHaveBeenCalledTimes(1))
    expect(updateProjectMock).not.toHaveBeenCalled()
    expect(createProjectMock.mock.calls[0][0]).toMatchObject({
      name: 'Acme rollout (copy)',
      product_lines: [{ product_category_id: 4 }],
      country_id: 1,
      pipeline_status_id: 3,
    })
    // REQUIREMENT CHANGED (user directive 2026-10-27): the untouched suggestion
    // is not sent, the server assigns the next free code at save time.
    expect(createProjectMock.mock.calls[0][0]).not.toHaveProperty('code')
  })

  it('does not overwrite the copied pipeline status with the system default', async () => {
    fetchSystemStatusIdMock.mockResolvedValue(42)
    renderCreate(projectFixture({ pipeline_status_id: 3 }))

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Status' }))
    expect(fetchSystemStatusIdMock).not.toHaveBeenCalled()
    expect(screen.getByTestId('select-Status')).toHaveTextContent('3')
  })
})
