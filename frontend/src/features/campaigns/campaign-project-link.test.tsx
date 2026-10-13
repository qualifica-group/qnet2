import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { CampaignDetailView } from '@/features/campaigns/campaign-detail'
import { CampaignForm } from '@/features/campaigns/campaign-form'
import {
  READ_ONLY,
  TEST_PROJECT_ID,
  campaignFixture,
  campaignWrapper,
  clickCreateSave,
  confirmOpenRow,
  linkedCampaignFixture,
  pencilOf,
  permissionsFor,
  pickProductLine,
  projectForSelectItem,
  typeInRow,
} from '@/features/campaigns/campaign-test-fixtures'
import type { CampaignDetailWithPermissions } from '@/features/campaigns/types'

/**
 * The Project row's cascade (spec 0221 D-3/D-4 on spec 0023 AC-042/AC-043 and
 * spec 0027 BR-5). REQUIREMENT CHANGED (spec 0221): the prefill is observed
 * on the rows and the payload instead of an always-open form, and unlinking
 * asks Stato, Righe and Area geografica in the Project row's own editor.
 * BR-3: the backend's 422 budget message is shown verbatim.
 */

const createCampaignMock = vi.fn()
const updateCampaignMock = vi.fn()
const fetchCampaignNextCodeMock = vi.fn<() => Promise<string>>()

vi.mock('@/features/campaigns/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/campaigns/api')>()),
  createCampaign: (...args: unknown[]) => createCampaignMock(...args),
  updateCampaign: (...args: unknown[]) => updateCampaignMock(...args),
  fetchCampaignNextCode: () => fetchCampaignNextCodeMock(),
}))

const fetchProjectsForSelectMock = vi.fn()
vi.mock('@/features/projects/for-select-api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/projects/for-select-api')>()),
  fetchProjectsForSelect: (...args: unknown[]) => fetchProjectsForSelectMock(...args),
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
  AsyncPaginatedSelect: (await import('@/features/campaigns/campaign-test-stubs')).StubAsyncSelect,
}))
vi.mock('@/features/product-lines/product-category-tree-select', async () => ({
  ProductCategoryTreeSelect: (await import('@/features/campaigns/campaign-test-stubs')).StubTreeSelect,
}))
vi.mock('@/features/product-lines/product-category-root-select', async () => ({
  ProductCategoryRootSelect: (await import('@/features/campaigns/campaign-test-stubs')).StubRootSelect,
}))
vi.mock('@/features/geo/geo-select', async () => ({
  GeoSelect: (await import('@/features/campaigns/campaign-test-stubs')).StubGeoSelect,
}))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

function renderCreate(source?: CampaignDetailWithPermissions) {
  const { client, wrapper } = campaignWrapper()
  render(<CampaignForm source={source} onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper })
  return client
}

function renderDetail(campaign: CampaignDetailWithPermissions) {
  const { client, wrapper } = campaignWrapper()
  render(<CampaignDetailView campaign={campaign} />, { wrapper })
  return client
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.restoreAllMocks()
  createCampaignMock.mockReset()
  updateCampaignMock.mockReset()
  fetchCampaignNextCodeMock.mockReset()
  fetchCampaignNextCodeMock.mockResolvedValue('CMP-0100')
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
  fetchSystemStatusIdMock.mockReset()
  fetchSystemStatusIdMock.mockResolvedValue(null)
  defaultCountryIdMock.mockReset()
  defaultCountryIdMock.mockReturnValue(null)
  fetchProjectsForSelectMock.mockReset()
  fetchProjectsForSelectMock.mockResolvedValue({
    items: [projectForSelectItem()],
    export_link: null,
    pagination: { total: 1, offset: 0, limit: 25, total_pages: 1 },
  })
})

describe('Campaign create — picking a Project (AC-042)', () => {
  it('prefills the cascade, sends the project-owned fields never and the own ones prefilled', async () => {
    createCampaignMock.mockResolvedValue(linkedCampaignFixture())
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    typeInRow('Name', 'Linked campaign')
    fireEvent.click(pencilOf('Project'))
    fireEvent.click(screen.getByTestId('select-Project'))
    await waitFor(() => expect(fetchProjectsForSelectMock).toHaveBeenCalled())
    confirmOpenRow('Done')

    // BR-2: the classification rows read the project's values, without a pencil.
    await waitFor(() => expect(screen.queryByRole('button', { name: 'Edit Status' })).not.toBeInTheDocument())
    expect(screen.queryByRole('button', { name: 'Edit Product lines' })).not.toBeInTheDocument()
    expect(screen.getByText('Inherited from the linked project.')).toBeInTheDocument()
    clickCreateSave()

    await waitFor(() => expect(createCampaignMock).toHaveBeenCalledTimes(1))
    const payload = createCampaignMock.mock.calls[0][0]
    expect(payload).toMatchObject({
      project_id: TEST_PROJECT_ID,
      partner_id: 31,
      operational_site_id: 81,
      start_date: '2026-03-15',
    })
    expect(payload).not.toHaveProperty('pipeline_status_id')
    expect(payload).not.toHaveProperty('product_lines')
    // BR-5: the country the project fills is locked, never sent.
    expect(payload).not.toHaveProperty('country_id')
  })

  it('reads the SELECTED project meta, not items[0]', async () => {
    fetchProjectsForSelectMock.mockResolvedValue({
      items: [{ ...projectForSelectItem(), id: 99, meta: { ...projectForSelectItem().meta, partner: { id: 5, label: 'Other' } } }, projectForSelectItem()],
      export_link: null,
      pagination: { total: 2, offset: 0, limit: 25, total_pages: 1 },
    })
    createCampaignMock.mockResolvedValue(linkedCampaignFixture())
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    typeInRow('Name', 'Linked campaign')
    fireEvent.click(pencilOf('Project'))
    fireEvent.click(screen.getByTestId('select-Project'))
    await waitFor(() => expect(fetchProjectsForSelectMock).toHaveBeenCalled())
    confirmOpenRow('Done')
    clickCreateSave()

    await waitFor(() => expect(createCampaignMock).toHaveBeenCalledTimes(1))
    expect(createCampaignMock.mock.calls[0][0].partner_id).toBe(31)
  })
})

describe('Campaign detail — the Project row (spec 0221 D-3/D-4)', () => {
  it('links a project in one save, carrying the prefilled cascade', async () => {
    updateCampaignMock.mockResolvedValue(linkedCampaignFixture())
    renderDetail(campaignFixture())

    fireEvent.click(pencilOf('Project'))
    fireEvent.click(screen.getByTestId('select-Project'))
    await waitFor(() => expect(fetchProjectsForSelectMock).toHaveBeenCalled())
    confirmOpenRow('Save')

    await waitFor(() => expect(updateCampaignMock).toHaveBeenCalledTimes(1))
    const payload = updateCampaignMock.mock.calls[0][1]
    expect(payload).toMatchObject({
      project_id: TEST_PROJECT_ID,
      partner_id: 31,
      operational_site_id: 81,
      start_date: '2026-03-15',
    })
    expect(payload).not.toHaveProperty('pipeline_status_id')
    expect(payload).not.toHaveProperty('product_lines')
  })

  it('asks Status, Product lines and Geography in the same editor when unlinking (AC-043), and sends them', async () => {
    updateCampaignMock.mockResolvedValue(campaignFixture())
    renderDetail(linkedCampaignFixture())

    fireEvent.click(pencilOf('Project'))
    expect(screen.queryByTestId('geo-select')).not.toBeInTheDocument()
    fireEvent.click(screen.getByTestId('clear-Project'))

    expect(screen.getByText('Without a project, status, product lines and geography belong to the campaign: fill them in here.')).toBeInTheDocument()
    fireEvent.click(screen.getByTestId('select-Status'))
    pickProductLine()
    fireEvent.click(screen.getByTestId('geo-select'))
    confirmOpenRow('Save')

    await waitFor(() => expect(updateCampaignMock).toHaveBeenCalledTimes(1))
    expect(updateCampaignMock.mock.calls[0][1]).toMatchObject({
      project_id: null,
      pipeline_status_id: 3,
      product_lines: [{ product_category_id: 4 }],
      country_id: 1,
    })
  })

  it('refuses the unlink while the re-required fields are empty', async () => {
    renderDetail(linkedCampaignFixture())

    fireEvent.click(pencilOf('Project'))
    fireEvent.click(screen.getByTestId('clear-Project'))
    confirmOpenRow('Save')

    // In the open editor and on the Geography row it belongs to.
    expect(await screen.findAllByText('Country is required when the linked project does not provide one.')).not.toHaveLength(0)
    expect(updateCampaignMock).not.toHaveBeenCalled()
  })

  it('offers no Project pencil when a field of its cascade is read-only (AC-003)', () => {
    renderDetail(campaignFixture({ permissions: permissionsFor({ code: READ_ONLY, partner_id: READ_ONLY }) }))

    expect(screen.queryByRole('button', { name: 'Edit Project' })).not.toBeInTheDocument()
  })

  it('keeps Status and Product lines without a pencil on a linked campaign (D-5)', () => {
    renderDetail(linkedCampaignFixture())

    expect(screen.queryByRole('button', { name: 'Edit Status' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Product lines' })).not.toBeInTheDocument()
    expect(pencilOf('Geography')).toBeInTheDocument()
  })

  it('locks the project-owned geo levels in the Geography editor, and has no pencil when the project fills them all', () => {
    renderDetail(linkedCampaignFixture())
    fireEvent.click(pencilOf('Geography'))
    expect(screen.getByTestId('geo-select')).toHaveAttribute('data-locked', 'country')
  })

  it('offers no Geography pencil when the project fills every level', () => {
    renderDetail(linkedCampaignFixture({ geo_locked_levels: ['country', 'state', 'province', 'city'] }))

    expect(screen.queryByRole('button', { name: 'Edit Geography' })).not.toBeInTheDocument()
  })
})

describe('Campaign detail — BR-3 budget 422', () => {
  it('shows the backend insufficient-budget message verbatim on the budget row', async () => {
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)
    updateCampaignMock.mockRejectedValue(
      Object.assign(new AxiosError('Unprocessable'), {
        response: {
          status: 422,
          data: { message: 'Invalid', errors: { total_budget: ['The project budget left is 400.00.'] } },
        },
      }),
    )
    renderDetail(linkedCampaignFixture())

    typeInRow('Total budget', '5000', 'Save')

    expect(await screen.findByText('The project budget left is 400.00.')).toBeInTheDocument()
    expect(screen.queryByText('Something went wrong. Please try again.')).not.toBeInTheDocument()
  })
})
