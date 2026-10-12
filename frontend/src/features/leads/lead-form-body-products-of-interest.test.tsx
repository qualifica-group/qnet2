import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import axios, { AxiosError } from 'axios'
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { LeadForm } from '@/features/leads/lead-form'
import { LeadDetailView } from '@/features/leads/lead-detail'
import type { ResourceMeta } from '@/features/authorization/types'
import {
  CAMPAIGN_OTHER,
  CAMPAIGN_PLAIN,
  PRODUCT_501,
  confirmOpenRow,
  leadFixture,
  leadWrapper,
  pencilOf,
  permissionsFor,
  pickInRow,
  stubState,
} from '@/features/leads/lead-test-fixtures'

/**
 * "Prodotti di interesse" (spec 0094): disabled until a Campaign is chosen,
 * scoped to its effective categories, and a Campaign switch that would orphan
 * a chosen product asks first (AC-042). REQUIREMENT CHANGED (spec 0217): the
 * products are a closed row of their own, and on the detail the scope comes
 * from the lead's Campaign itself (campaigns for-select `meta`).
 */

const createLeadMock = vi.fn()
const updateLeadMock = vi.fn()

vi.mock('@/features/leads/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/leads/api')>()),
  createLead: (...args: unknown[]) => createLeadMock(...args),
  updateLead: (...args: unknown[]) => updateLeadMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))

vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/leads/lead-test-stubs')).StubSelect,
}))
vi.mock('@/components/ui/async-paginated-multi-select', async () => ({
  AsyncPaginatedMultiSelect: (await import('@/features/leads/lead-test-stubs')).StubMultiSelect,
}))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
}))

const PRODUCTS = 'multi-Products of interest'

function renderCreate() {
  const { wrapper } = leadWrapper()
  render(<LeadForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper })
}

/** Waits until the coherence guard resolved product 501's category, so a switch is judged on settled data. */
async function settleProductLabels() {
  await waitFor(() =>
    expect(fetchForSelectMock).toHaveBeenCalledWith('products', expect.objectContaining({ ids: [PRODUCT_501.id] })),
  )
  const index = fetchForSelectMock.mock.calls.findIndex(
    ([resource, params]) =>
      resource === 'products' && JSON.stringify((params as { ids?: number[] })?.ids) === JSON.stringify([501]),
  )
  await act(async () => {
    await fetchForSelectMock.mock.results[index].value
  })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createLeadMock.mockReset()
  updateLeadMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
  stubState.campaigns = [CAMPAIGN_PLAIN, CAMPAIGN_OTHER]
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation((resource: string, params: { ids?: number[] }) => {
    const pool = resource === 'products' ? [PRODUCT_501] : resource === 'campaigns' ? [CAMPAIGN_PLAIN] : []
    return Promise.resolve({
      items: pool.filter((item) => params?.ids?.includes(item.id)),
      pagination: { offset: 0, limit: 25, total: 0 },
      export_link: null,
    })
  })
})

describe('Lead — Prodotti di interesse (spec 0094)', () => {
  it('AC-040: the picker is disabled until a Campaign is chosen', async () => {
    renderCreate()

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Products of interest' }))
    expect(screen.getByTestId(PRODUCTS)).toHaveAttribute('data-disabled', 'true')
    confirmOpenRow('Done')

    pickInRow('Campaign', 'Campaign')
    fireEvent.click(pencilOf('Products of interest'))
    expect(screen.getByTestId(PRODUCTS)).toHaveAttribute('data-disabled', 'false')
  })

  it("AC-041: the picker is scoped to the chosen Campaign's effective categories", async () => {
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Campaign' })

    pickInRow('Campaign', 'Campaign')
    fireEvent.click(pencilOf('Products of interest'))

    expect(screen.getByTestId(PRODUCTS)).toHaveAttribute('data-params', JSON.stringify({ category_ids: [10, 20] }))
  })

  it('AC-042: a Campaign switch orphaning a chosen product asks first and blocks Save', async () => {
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Campaign' })

    pickInRow('Campaign', 'Campaign')
    fireEvent.click(pencilOf('Products of interest'))
    fireEvent.click(screen.getByRole('button', { name: 'add product' }))
    confirmOpenRow('Done')
    await settleProductLabels()

    // CAMPAIGN_OTHER (category 99) no longer covers product 501 (category 10).
    fireEvent.click(pencilOf('Campaign'))
    fireEvent.click(screen.getByTestId('select-Campaign'))

    const dialog = await screen.findByRole('alertdialog')
    expect(dialog).toHaveTextContent('Widget')
    expect(screen.getAllByRole('button', { name: 'Save', hidden: true })[0]).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Done', hidden: true })).toBeDisabled()

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    // Cancelling keeps the previous campaign and the product untouched.
    await waitFor(() => expect(screen.getByRole('button', { name: 'Done' })).not.toBeDisabled())
    expect(screen.getByTestId('select-Campaign')).toHaveTextContent(String(CAMPAIGN_PLAIN.id))
    confirmOpenRow('Done')
    fireEvent.click(pencilOf('Products of interest'))
    expect(screen.getByTestId('multi-value')).toHaveTextContent('501')
  })

  it("scopes the detail picker on the lead's own Campaign, even with no product chosen yet", async () => {
    const { wrapper } = leadWrapper()
    render(<LeadDetailView lead={leadFixture({ products_of_interest: [] })} />, { wrapper })

    fireEvent.click(pencilOf('Products of interest'))

    await waitFor(() =>
      expect(screen.getByTestId(PRODUCTS)).toHaveAttribute('data-params', JSON.stringify({ category_ids: [10, 20] })),
    )
    expect(screen.getByTestId(PRODUCTS)).toHaveAttribute('data-disabled', 'false')
  })

  it('AC-043: a 422 on products_of_interest shows inside the open row', async () => {
    updateLeadMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: {
          success: false,
          message: 'Validation failed',
          errors: { products_of_interest: ['One of the chosen products is outside the campaign categories.'] },
        },
      } as never),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)
    const { wrapper } = leadWrapper()
    render(<LeadDetailView lead={leadFixture()} />, { wrapper })

    fireEvent.click(pencilOf('Products of interest'))
    await waitFor(() => expect(screen.getByTestId(PRODUCTS)).toHaveAttribute('data-disabled', 'false'))
    fireEvent.click(screen.getByRole('button', { name: 'add product' }))
    confirmOpenRow('Save')

    expect(
      await screen.findByText('One of the chosen products is outside the campaign categories.'),
    ).toBeInTheDocument()
    expect(updateLeadMock).toHaveBeenCalledWith(9, { products_of_interest: [PRODUCT_501.id] })
    vi.restoreAllMocks()
  })
})
