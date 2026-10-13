import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { CampaignDetailView } from '@/features/campaigns/campaign-detail'
import { CampaignForm } from '@/features/campaigns/campaign-form'
import {
  READ_ONLY,
  campaignFixture,
  campaignWrapper,
  clickCreateSave,
  clickInRow,
  completeRequiredCreateRows,
  confirmOpenRow,
  pencilOf,
  permissionsFor,
  pickProductLine,
  projectForSelectItem,
  typeInRow,
} from '@/features/campaigns/campaign-test-fixtures'
import type { CampaignDetailWithPermissions } from '@/features/campaigns/types'

/**
 * The campaign create form and the in-place detail (spec 0221): default
 * status and country preselection (spec 0039 D-3, spec 0221 D-6), the manual
 * code (spec 0025 AC-010..012), the product lines (spec 0132), Sede and Fonte.
 * REQUIREMENT CHANGED (spec 0221): rows are closed until opened, and the edit
 * cases run on the in-place detail.
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

describe('Campaign — default status and country (spec 0039 D-3, spec 0221 D-6)', () => {
  it('preselects the resolved "Nuovo" status id on a standalone create', async () => {
    fetchSystemStatusIdMock.mockResolvedValue(7)
    createCampaignMock.mockResolvedValue(campaignFixture())
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    completeRequiredCreateRows()
    clickCreateSave()

    await waitFor(() => expect(createCampaignMock).toHaveBeenCalledTimes(1))
    expect(createCampaignMock.mock.calls[0][0].pipeline_status_id).toBe(7)
  })

  it('takes the national default country without opening the geography row', async () => {
    defaultCountryIdMock.mockReturnValue(380)
    createCampaignMock.mockResolvedValue(campaignFixture())
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    typeInRow('Name', 'Spring push')
    fireEvent.click(pencilOf('Product lines'))
    pickProductLine()
    confirmOpenRow('Done')
    typeInRow('Start date', '2026-01-01')
    clickCreateSave()

    await waitFor(() => expect(createCampaignMock).toHaveBeenCalledTimes(1))
    expect(createCampaignMock.mock.calls[0][0].country_id).toBe(380)
  })

  it('refuses the save with the required message when no country is chosen', async () => {
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    typeInRow('Name', 'Spring push')
    clickCreateSave()

    expect(await screen.findByText('Country is required when the linked project does not provide one.')).toBeInTheDocument()
    expect(createCampaignMock).not.toHaveBeenCalled()
  })
})

describe('Campaign — manual code (spec 0025 AC-010/AC-011/AC-012)', () => {
  it('starts the create code row with the next sequential suggestion (AC-010)', async () => {
    renderCreate()

    expect(await screen.findAllByText('CMP-0100')).not.toHaveLength(0)
  })

  it('sends the trimmed manual code on create (AC-010)', async () => {
    createCampaignMock.mockResolvedValue(campaignFixture())
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    typeInRow('Code', '  CMP-CUSTOM  ')
    completeRequiredCreateRows()
    clickCreateSave()

    await waitFor(() => expect(createCampaignMock).toHaveBeenCalledTimes(1))
    expect(createCampaignMock.mock.calls[0][0].code).toBe('CMP-CUSTOM')
  })

  it('shows the saved code without a pencil on the detail and never sends it (AC-011)', async () => {
    updateCampaignMock.mockResolvedValue(campaignFixture({ name: 'Renamed' }))
    renderDetail(campaignFixture())

    expect(screen.getByText('CMP-0009', { selector: 'span' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Code' })).not.toBeInTheDocument()

    typeInRow('Name', 'Renamed', 'Save')

    await waitFor(() => expect(updateCampaignMock).toHaveBeenCalledWith(9, { name: 'Renamed' }))
  })

  it('maps a 422 on the code onto its row, not only a toast (AC-012)', async () => {
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)
    createCampaignMock.mockRejectedValue(
      Object.assign(new AxiosError('Unprocessable'), {
        response: { status: 422, data: { message: 'Invalid', errors: { code: ['The code has already been taken.'] } } },
      }),
    )
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    completeRequiredCreateRows()
    clickCreateSave()

    expect(await screen.findByText('The code has already been taken.')).toBeInTheDocument()
    expect(screen.queryByText('Something went wrong. Please try again.')).not.toBeInTheDocument()
  })
})

describe('Campaign — product lines, Sede and Fonte', () => {
  it('sends the complete product line, the picked Sede and Fonte on a standalone create', async () => {
    createCampaignMock.mockResolvedValue(campaignFixture())
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    completeRequiredCreateRows()
    clickInRow('Site', 'select-Site')
    clickInRow('Source', 'select-Source')
    clickCreateSave()

    await waitFor(() => expect(createCampaignMock).toHaveBeenCalledTimes(1))
    const payload = createCampaignMock.mock.calls[0][0]
    expect(payload.product_lines).toEqual([{ product_category_id: 4 }])
    expect(payload.operational_site_id).toBe(3)
    expect(payload.source_id).toBe(3)
  })

  it('creates with no Fonte chosen (optional, spec 0176 D-1)', async () => {
    createCampaignMock.mockResolvedValue(campaignFixture())
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    completeRequiredCreateRows()
    clickCreateSave()

    await waitFor(() => expect(createCampaignMock).toHaveBeenCalledTimes(1))
    expect(createCampaignMock.mock.calls[0][0].source_id).toBeNull()
  })

  it('refuses a standalone create without product lines', async () => {
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    typeInRow('Name', 'Spring push')
    clickInRow('Geography', 'geo-select')
    typeInRow('Start date', '2026-01-01')
    clickCreateSave()

    await waitFor(() => expect(screen.getAllByRole('alert').length).toBeGreaterThan(0))
    expect(createCampaignMock).not.toHaveBeenCalled()
  })

  it('offers no pencil on a field the actor cannot edit', () => {
    renderDetail(campaignFixture({ permissions: permissionsFor({ code: READ_ONLY, source_id: READ_ONLY }) }))

    expect(screen.queryByRole('button', { name: 'Edit Source' })).not.toBeInTheDocument()
    expect(pencilOf('Site')).toBeInTheDocument()
  })
})
