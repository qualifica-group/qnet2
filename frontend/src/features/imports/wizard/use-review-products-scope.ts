import { useMemo } from 'react'
import { CAMPAIGNS_FOR_SELECT_RESOURCE } from '@/features/campaigns/for-select-api'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { dependencyProductCategoryIds } from '@/features/imports/wizard/import-config-dependency'
import type { ImportRunDetail } from '@/features/imports/wizard/types'

/** `global_fields` id carrying the run's default products of interest (spec 0094 D-4). */
const GLOBAL_PRODUCTS_FIELD_ID = 'product_ids'

/** `global_fields` id carrying the run's campaign — `product_ids` is scoped by its effective categories. */
const GLOBAL_CAMPAIGN_FIELD_ID = 'campaign_id'

/** Reads the run's global default products (`global_config.product_ids`), or `[]` when unset. */
function resolveGlobalDefaultProductIds(run: ImportRunDetail): number[] {
  const raw = run.global_config?.[GLOBAL_PRODUCTS_FIELD_ID]
  return Array.isArray(raw) ? raw.filter((id): id is number => typeof id === 'number') : []
}

/** Reads the run's campaign id (`global_config.campaign_id`), or `null` when unset. */
function resolveCampaignId(run: ImportRunDetail): number | null {
  const raw = run.global_config?.[GLOBAL_CAMPAIGN_FIELD_ID]
  return typeof raw === 'number' ? raw : null
}

/**
 * Resolves the review grid's products context (spec 0094 D-4/AC-055):
 * whether the run carries a global `product_ids` default, and the run's
 * campaign EFFECTIVE product-category ids — the same `meta.product_category_ids`
 * contract `ProductsOfInterestField` scopes on, read here via the same
 * ids-keyed for-select label query a relation select uses for its own
 * trigger label, so no extra request is added beyond what a normal campaign
 * picker already costs.
 */
export function useReviewProductsScope(run: ImportRunDetail) {
  const globalDefaultProductIds = useMemo(() => resolveGlobalDefaultProductIds(run), [run])
  const campaignId = useMemo(() => resolveCampaignId(run), [run])

  const campaignItems = useForSelectLabels({
    resource: CAMPAIGNS_FOR_SELECT_RESOURCE,
    ids: campaignId != null ? [campaignId] : [],
    enabled: campaignId != null,
  })

  const campaignCategoryIds = useMemo(
    () => dependencyProductCategoryIds(campaignId != null ? (campaignItems.get(campaignId) ?? null) : null),
    [campaignItems, campaignId],
  )

  return { globalDefaultProductIds, campaignCategoryIds }
}
