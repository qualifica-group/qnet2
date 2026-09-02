import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, fireEvent, renderHook, screen, waitFor, within } from '@testing-library/react'
import type { ReactNode } from 'react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import type { ForSelectItem } from '@/features/for-select/types'
import { useLeadCampaignProductInterest } from '@/features/leads/use-lead-campaign-product-interest'
import type { LeadFormValues } from '@/features/leads/use-lead-form'

/**
 * Spec 0094, D-5/AC-041/AC-042: the Lead's Campaign <-> Prodotti di interesse
 * coherence guard, tested directly (mirrors `use-products-of-interest-coherence.test.tsx`'s
 * `renderHook` approach) rather than through the full form, so the async
 * product-label resolution never races a synthetic click.
 */

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>(
    '@/features/for-select/api',
  )
  return {
    ...actual,
    fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
  }
})

const PRODUCTS = [
  { id: 501, label: 'Widget', meta: { category_id: 10 } },
  { id: 502, label: 'Gadget', meta: { category_id: 99 } },
]

const CAMPAIGN_A = { id: 20, label: 'Spring push', meta: { product_category_ids: [10] } } as ForSelectItem
const CAMPAIGN_B = { id: 30, label: 'Autumn push', meta: { product_category_ids: [99] } } as ForSelectItem

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

/** Create-mode shape (`original: null`): every id starts at `null`, matching `use-lead-form`'s own create defaults. */
function renderGuard(productIds: number[], onCampaignApplied = vi.fn()) {
  return renderHook(
    () => {
      const form = useForm<LeadFormValues>({
        defaultValues: {
          registry_id: 1,
          campaign_id: null,
          operational_site_id: null,
          source_id: null,
          operator_id: null,
          state_id: null,
          notes: null,
          extra_fields: [],
          products_of_interest: productIds,
          convert_to_opportunity: false,
        },
      })
      const guard = useLeadCampaignProductInterest({ form, original: null, onCampaignApplied })
      return { form, ...guard }
    },
    { wrapper: wrapper() },
  )
}

/** Awaits the exact resolved for-select response for `ids`, then flushes the resulting react-query state update. */
async function waitForProductLabels(ids: number[]) {
  await waitFor(() =>
    expect(fetchForSelectMock).toHaveBeenCalledWith('products', expect.objectContaining({ ids })),
  )
  const callIndex = fetchForSelectMock.mock.calls.findIndex(
    ([resource, params]) =>
      resource === 'products' && JSON.stringify((params as { ids?: number[] })?.ids) === JSON.stringify(ids),
  )
  await act(async () => {
    await fetchForSelectMock.mock.results[callIndex].value
  })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation((_resource: string, params: { ids?: number[] }) =>
    Promise.resolve({
      items: PRODUCTS.filter((product) => params?.ids?.includes(product.id)),
      pagination: { offset: 0, limit: 25, total: params?.ids?.length ?? 0 },
      export_link: null,
    }),
  )
})

describe('useLeadCampaignProductInterest (spec 0094, D-5)', () => {
  it('AC-041: resolves categoryIds from the picked campaign meta and applies immediately with nothing selected', async () => {
    const onApplied = vi.fn()
    const { result } = renderGuard([], onApplied)

    // `AsyncPaginatedSelect.select()` always applies `onChange(item.id)`
    // BEFORE invoking `onItemChange` (the guard under test) — replicated here.
    act(() => {
      result.current.form.setValue('campaign_id', CAMPAIGN_A.id)
      void result.current.handleCampaignItemChange(CAMPAIGN_A)
    })

    await waitFor(() => expect(result.current.campaignCategoryIds).toEqual([10]))
    expect(onApplied).toHaveBeenCalledWith(CAMPAIGN_A)
    expect(result.current.isCampaignChangePending).toBe(false)
  })

  it('applies a clear (null item) immediately when nothing is at risk', async () => {
    const onApplied = vi.fn()
    const { result } = renderGuard([], onApplied)

    act(() => {
      void result.current.handleCampaignItemChange(null)
    })

    await waitFor(() => expect(onApplied).toHaveBeenCalledWith(null))
    expect(result.current.campaignCategoryIds).toEqual([])
  })

  it('keeps a covered product across a switch with no incompatibility', async () => {
    const onApplied = vi.fn()
    const { result } = renderGuard([501], onApplied)
    await waitForProductLabels([501])

    act(() => {
      result.current.form.setValue('campaign_id', CAMPAIGN_A.id)
      void result.current.handleCampaignItemChange(CAMPAIGN_A)
    })

    await waitFor(() => expect(onApplied).toHaveBeenCalledWith(CAMPAIGN_A))
    expect(result.current.form.getValues('products_of_interest')).toEqual([501])
    expect(result.current.isCampaignChangePending).toBe(false)
  })

  describe('AC-042: an incompatible switch is held back for explicit confirmation', () => {
    /** Picks the FIRST campaign (no conflict, sets the guard's own "previous campaign" baseline), then selects a product it covers. */
    async function withCampaignAAndProduct501(onApplied: Parameters<typeof renderGuard>[1]) {
      const view = renderGuard([], onApplied)
      act(() => {
        view.result.current.form.setValue('campaign_id', CAMPAIGN_A.id)
        void view.result.current.handleCampaignItemChange(CAMPAIGN_A)
      })
      await waitFor(() => expect(view.result.current.campaignCategoryIds).toEqual([10]))

      act(() => {
        view.result.current.form.setValue('products_of_interest', [501])
      })
      await waitForProductLabels([501])

      return view
    }

    it('reverts campaign_id, flags the pending state, and blocks the switch until confirmed', async () => {
      const onApplied = vi.fn()
      const { result } = await withCampaignAAndProduct501(onApplied)
      onApplied.mockClear()

      let pending!: Promise<void>
      act(() => {
        result.current.form.setValue('campaign_id', CAMPAIGN_B.id)
        pending = result.current.handleCampaignItemChange(CAMPAIGN_B)
      })

      // Held back: campaign_id reverted to the Campaign A baseline, nothing
      // applied yet, Submit gated via `isCampaignChangePending`.
      expect(result.current.form.getValues('campaign_id')).toBe(CAMPAIGN_A.id)
      expect(result.current.isCampaignChangePending).toBe(true)
      expect(onApplied).not.toHaveBeenCalled()

      const dialog = await screen.findByRole('alertdialog')
      expect(dialog).toHaveTextContent('Widget')

      await act(async () => {
        fireEvent.click(within(dialog).getByRole('button', { name: 'Remove and continue' }))
        await pending
      })

      expect(result.current.form.getValues('campaign_id')).toBe(CAMPAIGN_B.id)
      expect(result.current.form.getValues('products_of_interest')).toEqual([])
      expect(result.current.campaignCategoryIds).toEqual([99])
      expect(result.current.isCampaignChangePending).toBe(false)
      expect(onApplied).toHaveBeenCalledWith(CAMPAIGN_B)
    })

    it('cancelling leaves the campaign and the products untouched', async () => {
      const onApplied = vi.fn()
      const { result } = await withCampaignAAndProduct501(onApplied)
      onApplied.mockClear()

      let pending!: Promise<void>
      act(() => {
        result.current.form.setValue('campaign_id', CAMPAIGN_B.id)
        pending = result.current.handleCampaignItemChange(CAMPAIGN_B)
      })

      const dialog = await screen.findByRole('alertdialog')

      await act(async () => {
        fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }))
        await pending
      })

      expect(result.current.form.getValues('campaign_id')).toBe(CAMPAIGN_A.id)
      expect(result.current.form.getValues('products_of_interest')).toEqual([501])
      expect(result.current.isCampaignChangePending).toBe(false)
      expect(onApplied).not.toHaveBeenCalled()
    })
  })
})
