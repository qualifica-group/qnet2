import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch, type UseFormReturn } from 'react-hook-form'
import { useQueryClient, type QueryClient } from '@tanstack/react-query'
import { useConfirm } from '@/components/confirm-dialog-context'
import type { CampaignForSelectItem } from '@/features/campaigns/for-select-api'
import { fetchForSelect } from '@/features/for-select/api'
import { forSelectKeys } from '@/features/for-select/query-keys'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import type { ForSelectItem } from '@/features/for-select/types'
import { PRODUCTS_FOR_SELECT_RESOURCE, productCategoryIdOf } from '@/features/products/for-select-api'
import type { LeadDetail, LeadProductOfInterest } from '@/features/leads/types'
import type { LeadFormValues } from '@/features/leads/use-lead-form'

/** Same freshness window as `useForSelectLabels`: a resolved product's category is stable within a session. */
const PRODUCT_LABELS_STALE_TIME_MS = 5 * 60 * 1000

/**
 * `meta.product_category_ids` on a campaign for-select item (spec 0094
 * `data_contract`): the campaign's EFFECTIVE categories, already resolved
 * server-side via the linked project when applicable. The block is optional
 * and comes off the wire, so the contents are still validated at runtime the
 * same way `productCategoryIdOf` (`for-select-api.ts`) reads a product's own
 * category from its `meta`.
 */
function campaignProductCategoryIds(item: ForSelectItem | null): number[] {
  const ids = (item as CampaignForSelectItem | null)?.meta?.product_category_ids

  return Array.isArray(ids) ? ids.filter((id): id is number => typeof id === 'number') : []
}

/**
 * Awaited, cache-sharing lookup of `ids`' `ForSelectItem`s — same query key
 * `AsyncPaginatedMultiSelect`'s own label hydration would warm for these ids
 * (`for-select-api`'s label-query shape), read here imperatively via
 * `QueryClient.fetchQuery` so an AC-042 compatibility decision is always
 * grounded in settled data. A passively-rendered `useQuery` Map can
 * legitimately lag one render behind a just-resolved fetch (the query's own
 * notify cycle needs its own turn), which would silently treat a
 * just-added product as "no risk" and skip the confirmation below — a real
 * race, not a test artifact. A failed fetch resolves to an empty map: the
 * caller already treats an unresolved product as no risk (the server has the
 * last word), so a network error degrades the same way, never throwing into
 * the campaign switch.
 */
async function resolveProductLabels(
  queryClient: QueryClient,
  ids: number[],
): Promise<Map<number, ForSelectItem>> {
  if (ids.length === 0) {
    return new Map()
  }

  const sortedIds = [...ids].sort((a, b) => a - b)

  try {
    const page = await queryClient.fetchQuery({
      queryKey: forSelectKeys.labels(PRODUCTS_FOR_SELECT_RESOURCE, sortedIds),
      queryFn: () =>
        fetchForSelect(PRODUCTS_FOR_SELECT_RESOURCE, {
          offset: 0,
          limit: Math.min(Math.max(sortedIds.length, 1), 100),
          ids: sortedIds,
        }),
      staleTime: PRODUCT_LABELS_STALE_TIME_MS,
    })

    return new Map(page.items.map((product) => [product.id, product]))
  } catch {
    return new Map()
  }
}

/** Distinct category ids already known from the lead's loaded products (edit mode), no extra request. */
function uniqueCategoryIds(products: LeadProductOfInterest[]): number[] {
  return [
    ...new Set(
      products.map((product) => product.product_category?.id).filter((id): id is number => id != null),
    ),
  ]
}

interface UseLeadCampaignProductInterestArgs {
  form: UseFormReturn<LeadFormValues>
  original: LeadDetail | null
  /**
   * Runs the rest of a campaign switch (the Sede prefill, project -> campaign
   * -> lead chain) once this hook decides it may proceed — immediately when
   * the current selection stays covered, or after the operator's explicit
   * confirmation otherwise (AC-042).
   */
  onCampaignApplied: (item: ForSelectItem | null) => void
}

/**
 * Guards the Lead's "Prodotti di interesse" <-> Campagna coherence (spec
 * 0094, D-5): the picker is scoped to the CHOSEN campaign's effective
 * categories, read from the same for-select payload the trigger already
 * resolved (AC-041, no extra request). Changing the Campaign while carrying
 * products it no longer covers must NOT silently drop them — unlike the
 * Opportunity form's `useProductsOfInterestCoherence` (auto-prune on a row
 * edit the operator made themselves), here the categories move because of a
 * DIFFERENT field, so D-5 requires an explicit confirmation: the switch is
 * held back, `campaign_id` reverted, and an imperative confirm (`useConfirm`)
 * names the affected products. Confirming applies the switch and prunes
 * them; cancelling leaves the campaign untouched (AC-042: "senza conferma il
 * submit non parte" is enforced for free by the confirm dialog's own focus
 * trap, which blocks every other interaction, Submit included, until it
 * resolves).
 */
export function useLeadCampaignProductInterest({
  form,
  original,
  onCampaignApplied,
}: UseLeadCampaignProductInterestArgs) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const queryClient = useQueryClient()

  const [campaignCategoryIds, setCampaignCategoryIds] = useState<number[]>(() =>
    uniqueCategoryIds(original?.products_of_interest ?? []),
  )
  // Explicit gate for AC-042 ("senza conferma il submit non parte"): the
  // confirm dialog's own focus trap already blocks other interaction in a
  // real browser, but the caller disables Submit on this flag too so the
  // rule holds deterministically, test environment included.
  const [isCampaignChangePending, setIsCampaignChangePending] = useState(false)
  const previousCampaignIdRef = useRef<number | null>(original?.campaign_id ?? null)

  const productIds = useWatch({ control: form.control, name: 'products_of_interest' })

  const handleCampaignItemChange = useCallback(
    async (item: ForSelectItem | null) => {
      const nextCategoryIds = campaignProductCategoryIds(item)

      // Step 1: resolve the CURRENTLY-selected products' categories fresh and
      // awaited (see `resolveProductLabels`), then keep only the ones the new
      // campaign no longer covers. A product whose category still cannot be
      // resolved (fetch failed) is KEPT out of the count: blocking on
      // incomplete information would be worse than letting the server have
      // the last word (mirrors the opportunity form's coherence hook).
      const resolvedProducts = await resolveProductLabels(queryClient, productIds)
      const incompatible = productIds
        .map((id) => resolvedProducts.get(id))
        .filter((product): product is ForSelectItem => {
          if (!product) return false
          const categoryId = productCategoryIdOf(product)
          return categoryId !== null && !nextCategoryIds.includes(categoryId)
        })

      // Step 2: nothing at risk — apply the switch immediately, like any
      // other field.
      if (incompatible.length === 0) {
        setCampaignCategoryIds(nextCategoryIds)
        previousCampaignIdRef.current = item?.id ?? null
        onCampaignApplied(item)
        return
      }

      // Step 3: revert the id `AsyncPaginatedSelect.onChange` already applied
      // before this handler ran, so the form never carries a half-applied
      // campaign while the confirm is pending.
      form.setValue('campaign_id', previousCampaignIdRef.current, { shouldValidate: true })
      setIsCampaignChangePending(true)

      const confirmed = await confirm({
        title: t('leads.form.productsOfInterest.campaignChangeTitle'),
        description: t('leads.form.productsOfInterest.campaignChangeDescription', {
          names: incompatible.map((product) => product.label).join(', '),
        }),
        confirmLabel: t('leads.form.productsOfInterest.campaignChangeConfirm'),
        tone: 'warning',
      })

      setIsCampaignChangePending(false)

      if (!confirmed) {
        return
      }

      // Step 4: confirmed — re-apply the id Step 3 reverted, then drop
      // exactly the named products.
      form.setValue('campaign_id', item?.id ?? null, { shouldValidate: true })
      const dropIds = new Set(incompatible.map((product) => product.id))
      form.setValue(
        'products_of_interest',
        productIds.filter((id) => !dropIds.has(id)),
        { shouldDirty: true },
      )
      setCampaignCategoryIds(nextCategoryIds)
      previousCampaignIdRef.current = item?.id ?? null
      onCampaignApplied(item)
    },
    [confirm, form, onCampaignApplied, productIds, queryClient, t],
  )

  return { campaignCategoryIds, isCampaignChangePending, handleCampaignItemChange }
}
