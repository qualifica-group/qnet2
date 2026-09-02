import type { CampaignForSelectMeta } from '@/features/campaigns/for-select-api'
import type { ForSelectItem } from '@/features/for-select/types'

/**
 * Reads `meta.product_category_ids` off a dependency for-select item (spec
 * 0094 `data_contract`): the only cross-field dependency the import wizard's
 * global config has today — `product_ids` depends on `campaign_id`, scoped
 * by the chosen campaign's EFFECTIVE categories. Mirrors
 * `campaignProductCategoryIds` in
 * `features/leads/use-lead-campaign-product-interest.ts`, kept as its own
 * copy since this feature never imports from `features/leads`.
 */
export function dependencyProductCategoryIds(item: ForSelectItem | null | undefined): number[] {
  const ids = (item as (ForSelectItem & { meta?: CampaignForSelectMeta }) | null | undefined)?.meta
    ?.product_category_ids
  return Array.isArray(ids) ? ids.filter((id): id is number => typeof id === 'number') : []
}
