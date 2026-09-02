import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the campaigns for-select endpoint (spec 0024, ADR 0011). */
export const CAMPAIGNS_FOR_SELECT_RESOURCE = 'campaigns'

/**
 * The linked Sede's `{id, label}` identity inside `CampaignForSelectItem.meta`
 * (project -> campaign -> lead prefill chain), so a picker built on this
 * resource can prefill the Lead's `operational_site_id` without a second
 * fetch. No Regione here: the Lead's Regione is a free, never-inherited
 * field (user decision, supersedes the earlier "auto-fill from Sede" spec).
 */
export interface CampaignForSelectOperationalSite {
  id: number
  label: string
}

/** The `meta` block carried by every `/campaigns/for-select` item, feeding the Lead form's Sede prefill on selection. */
export interface CampaignForSelectMeta {
  operational_site: CampaignForSelectOperationalSite | null
  /**
   * The EFFECTIVE product categories the campaign classifies itself with —
   * already resolved through the linked project when there is one (spec 0094).
   * The Lead form scopes its "prodotti di interesse" picker on these, so it
   * never needs a second request to know which products are pickable.
   */
  product_category_ids?: number[]
}

/** A single campaign option as returned by `GET /api/campaigns/for-select`, carrying its `meta` default-population block. */
export interface CampaignForSelectItem extends ForSelectItem {
  meta?: CampaignForSelectMeta
}

/**
 * Fetches a page of campaign options from `GET /api/campaigns/for-select`.
 * Thin wrapper over the generic for-select fetcher, bound to the `campaigns`
 * resource. Items carry `label` (name) and `subtitle` (code).
 */
export function fetchCampaignsForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(CAMPAIGNS_FOR_SELECT_RESOURCE, params)
}

interface UseCampaignsForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a campaign single-select: debounced server search,
 * offset pagination and `ids[]` hydration, bound to the `campaigns` resource.
 */
export function useCampaignsForSelect({ search, ids, enabled }: UseCampaignsForSelectOptions) {
  return useForSelect({
    resource: CAMPAIGNS_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
