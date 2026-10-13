import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { ProjectDetailView } from '@/features/projects/project-detail'
import { ProjectForm } from '@/features/projects/project-form'
import {
  READ_ONLY,
  clickCreateSave,
  clickInRow,
  completeRequiredCreateRows,
  confirmOpenRow,
  inputOf,
  pencilOf,
  permissionsFor,
  projectFixture,
  projectWrapper,
  typeInRow,
} from '@/features/projects/project-test-fixtures'
import type { ProjectDetailWithPermissions } from '@/features/projects/types'

/**
 * The project create form and the in-place detail (spec 0220): default status
 * preselection (spec 0039 D-3), the manual code (spec 0025 AC-010..012), the
 * geo hierarchy (spec 0027 BR-4) and the date order (BR-6). REQUIREMENT
 * CHANGED (spec 0220): rows are closed until opened, and the edit cases run on
 * the in-place detail.
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

const defaultCountryIdMock = vi.fn<() => number | null>()
vi.mock('@/features/geo/use-default-country', () => ({
  useDefaultCountryId: () => defaultCountryIdMock(),
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
  defaultCountryIdMock.mockReset()
  defaultCountryIdMock.mockReturnValue(null)
})

describe('Project — default status preselection (spec 0039 D-3, AC-012)', () => {
  it('preselects the resolved "Nuovo" status id on create once it resolves', async () => {
    fetchSystemStatusIdMock.mockResolvedValue(42)
    renderCreate()

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Status' }))
    await waitFor(() => expect(screen.getByTestId('select-Status')).toHaveTextContent('42'))
  })

  it('does not preselect on the detail', () => {
    fetchSystemStatusIdMock.mockResolvedValue(42)
    renderDetail(projectFixture())

    fireEvent.click(pencilOf('Status'))
    expect(screen.getByTestId('select-Status')).toHaveTextContent('3')
    expect(fetchSystemStatusIdMock).not.toHaveBeenCalled()
  })
})

describe('Project — manual code (spec 0025 AC-010/AC-011)', () => {
  it('starts the create code row with the next sequential suggestion (AC-010)', async () => {
    fetchProjectNextCodeMock.mockResolvedValue('PRJ-0042')
    renderCreate()

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Code' }))
    expect(inputOf('Code')).toHaveValue('PRJ-0042')
  })

  it('sends the trimmed manual code on create (AC-010)', async () => {
    createProjectMock.mockResolvedValue(projectFixture({ code: 'ACME-2026' }))
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    typeInRow('Code', '  ACME-2026  ')
    completeRequiredCreateRows()
    clickCreateSave()

    await waitFor(() => expect(createProjectMock).toHaveBeenCalledTimes(1))
    expect(createProjectMock.mock.calls[0][0].code).toBe('ACME-2026')
  })

  it('shows the saved code without a pencil on the detail and never sends it (AC-011)', async () => {
    updateProjectMock.mockResolvedValue(projectFixture({ name: 'Renamed rollout' }))
    renderDetail(projectFixture())

    expect(screen.queryByRole('button', { name: 'Edit Code' })).not.toBeInTheDocument()
    typeInRow('Name', 'Renamed rollout', 'Save')

    await waitFor(() => expect(updateProjectMock).toHaveBeenCalledWith(7, { name: 'Renamed rollout' }))
  })
})

describe('Project — 422 duplicate code (spec 0025 AC-012)', () => {
  it('maps a 422 on the code onto its row, not only a toast', async () => {
    createProjectMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed.', errors: { code: ['The code has already been taken.'] } },
      } as never),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    completeRequiredCreateRows()
    clickCreateSave()

    expect(await screen.findByText('The code has already been taken.')).toBeInTheDocument()
    expect(screen.queryByText('Something went wrong. Please try again.')).not.toBeInTheDocument()
  })
})

describe('Project — geo hierarchy (spec 0027 BR-4/AC-010)', () => {
  it('refuses the save with the required message when no country is chosen', async () => {
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    typeInRow('Name', 'Acme rollout')
    clickCreateSave()

    expect(await screen.findByText('Country is required.')).toBeInTheDocument()
    expect(createProjectMock).not.toHaveBeenCalled()
  })

  it('sends the chosen country on create', async () => {
    createProjectMock.mockResolvedValue(projectFixture())
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    completeRequiredCreateRows()
    clickCreateSave()

    await waitFor(() => expect(createProjectMock).toHaveBeenCalledTimes(1))
    expect(createProjectMock.mock.calls[0][0].country_id).toBe(1)
  })

  it('takes the national default country on create without opening the geography row', async () => {
    defaultCountryIdMock.mockReturnValue(380)
    createProjectMock.mockResolvedValue(projectFixture())
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    typeInRow('Name', 'Acme rollout')
    clickInRow('Status', 'select-Status')
    fireEvent.click(pencilOf('Product lines'))
    fireEvent.click(screen.getByTestId('select-Parent category 1'))
    fireEvent.click(screen.getByTestId('select-Product category 1'))
    confirmOpenRow('Done')
    typeInRow('Start date', '2026-01-01')
    clickCreateSave()

    await waitFor(() => expect(createProjectMock).toHaveBeenCalledTimes(1))
    expect(createProjectMock.mock.calls[0][0].country_id).toBe(380)
  })

  it('edits the whole cascade as one row on the detail, all four levels gated together', () => {
    renderDetail(projectFixture({ permissions: permissionsFor({ code: READ_ONLY, city_id: READ_ONLY }) }))

    expect(screen.queryByRole('button', { name: 'Edit Geography' })).not.toBeInTheDocument()
  })
})

describe('Project — end_date validation (BR-6)', () => {
  it('rejects on the detail an end_date earlier than the start_date', async () => {
    renderDetail(projectFixture())

    typeInRow('End date', '2025-06-01', 'Save')

    expect(await screen.findByText('End date must not be earlier than the start date.')).toBeInTheDocument()
    expect(updateProjectMock).not.toHaveBeenCalled()
  })
})
