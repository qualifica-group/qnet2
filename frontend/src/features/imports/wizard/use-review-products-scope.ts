import { useMemo } from 'react'
import { CAMPAIGNS_FOR_SELECT_RESOURCE } from '@/features/campaigns/for-select-api'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { dependencyProductCategoryIds } from '@/features/imports/wizard/import-config-dependency'
import type { ImportRunDetail } from '@/features/imports/wizard/types'

/** `global_fields` id carrying the run's default products of interest (spec 0094 D-4). */
const GLOBAL_PRODUCTS_FIELD_ID = 'product_ids'

/** `global_fields` id carrying the run's campaign — `product_ids` is scoped by its effective categories. */
const GLOBAL_CAMPAIGN_FIELD_ID = 'campaign_id'

/** `global_fields` id carrying the run's product line (category) when there is no campaign (spec 0348). */
const GLOBAL_CATEGORY_FIELD_ID = 'product_category_id'

/** Hoisted so the no-scope result keeps a stable reference. */
const EMPTY_CATEGORY_IDS: number[] = []

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

/** Reads the run's product line (`global_config.product_category_id`), or `null` when unset. */
function resolveCategoryId(run: ImportRunDetail): number | null {
  const raw = run.global_config?.[GLOBAL_CATEGORY_FIELD_ID]
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

  // Without a campaign (request-management import) the run's own product
  // line scopes the products picker.
  const runCategoryId = useMemo(() => resolveCategoryId(run), [run])
  const campaignCategoryIds = useMemo(() => {
    if (campaignId == null) {
      return runCategoryId != null ? [runCategoryId] : EMPTY_CATEGORY_IDS
    }
    return dependencyProductCategoryIds(campaignItems.get(campaignId) ?? null)
  }, [campaignItems, campaignId, runCategoryId])

  return { globalDefaultProductIds, campaignCategoryIds }
}
