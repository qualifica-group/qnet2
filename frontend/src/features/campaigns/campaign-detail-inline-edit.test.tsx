import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { CampaignDetailView } from '@/features/campaigns/campaign-detail'
import {
  READ_ONLY,
  campaignFixture,
  campaignWrapper,
  clickInRow,
  inputOf,
  pencilOf,
  permissionsFor,
  projectForSelectItem,
  typeInRow,
} from '@/features/campaigns/campaign-test-fixtures'
import type { CampaignDetailWithPermissions } from '@/features/campaigns/types'

/**
 * Spec 0221: the campaign detail edits in place, one row at a time. Each save
 * is a PATCH of that field alone — the geography row sends the levels of its
 * cascade that changed.
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

describe('CampaignDetailView — in-place editing (spec 0221)', () => {
  it('PATCHes the edited field alone and seeds the cache with the permissions', async () => {
    const saved = campaignFixture({ target_lead: 50 })
    updateCampaignMock.mockResolvedValue(saved)
    const client = renderDetail(campaignFixture())

    typeInRow('Target leads', '50', 'Save')

    await waitFor(() => expect(updateCampaignMock).toHaveBeenCalledWith(9, { target_lead: 50 }))
    expect(client.getQueryData<CampaignDetailWithPermissions>(['campaigns', 'detail', 9])?.permissions).toEqual(
      saved.permissions,
    )
  })

  it('sends the geography cascade as one change', async () => {
    updateCampaignMock.mockResolvedValue(campaignFixture())
    renderDetail(campaignFixture())

    clickInRow('Geography', 'geo-select', 'Save')

    await waitFor(() => expect(updateCampaignMock).toHaveBeenCalledWith(9, { country_id: 1 }))
  })

  it('closes on Cancel without calling the server', () => {
    renderDetail(campaignFixture())

    fireEvent.click(pencilOf('Name'))
    fireEvent.change(inputOf('Name'), { target: { value: 'Changed' } })
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(screen.getAllByText('Spring push').length).toBeGreaterThan(0)
    expect(updateCampaignMock).not.toHaveBeenCalled()
  })

  it('offers no pencil on a field the actor cannot edit', () => {
    renderDetail(campaignFixture({ permissions: permissionsFor({ code: READ_ONLY, name: READ_ONLY }) }))

    expect(screen.queryByRole('button', { name: 'Edit Name' })).not.toBeInTheDocument()
    expect(pencilOf('Description')).toBeInTheDocument()
  })

  it('offers no Edit action: the record edits in place (spec 0221 AC-001)', () => {
    renderDetail(campaignFixture())

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
  })
})
