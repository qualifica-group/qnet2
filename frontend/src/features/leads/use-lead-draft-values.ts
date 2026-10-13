import { useMemo } from 'react'
import { useWatch, type Control } from 'react-hook-form'
import { CAMPAIGNS_FOR_SELECT_RESOURCE } from '@/features/campaigns/for-select-api'
import type { ForSelectItem } from '@/features/for-select/types'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { PRODUCTS_FOR_SELECT_RESOURCE, productCategoryIdOf } from '@/features/products/for-select-api'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { entriesToRecord } from '@/features/leads/extra-fields'
import type { LeadRecordValues } from '@/features/leads/lead-record'
import type { LeadProductOfInterest } from '@/features/leads/types'
import type { LeadFormValues } from '@/features/leads/use-lead-form'

/** Shown while a label resolves: never the bare id. */
const PENDING_LABEL = '…'

/** Stable empty set: a fresh `[]` per render would break the label hooks' memo. */
const NO_IDS: number[] = []

function idsOf(id: number | null | undefined): number[] {
  return id != null ? [id] : NO_IDS
}

function nameOf(labels: Map<number, ForSelectItem>, id: number): string {
  return labels.get(id)?.label ?? PENDING_LABEL
}

/** A for-select product as the record's product rows read it: its category is the item's subtitle. */
function productOf(id: number, item: ForSelectItem | undefined): LeadProductOfInterest {
  const categoryId = item ? productCategoryIdOf(item) : null
  return {
    id,
    name: item?.label ?? PENDING_LABEL,
    product_category: categoryId !== null ? { id: categoryId, name: item?.subtitle ?? '' } : null,
  }
}

/**
 * The create draft as the record's closed rows read it (spec 0217): the form
 * holds ids only, so every relation is named through `useForSelectLabels` —
 * the very cache the pickers fill for their own trigger.
 */
export function useLeadDraftValues(control: Control<LeadFormValues>): LeadRecordValues {
  const draft = useWatch({ control })
  const productIds = useMemo(
    () => (draft.products_of_interest?.length ? (draft.products_of_interest as number[]) : NO_IDS),
    [draft.products_of_interest],
  )

  const registries = useForSelectLabels({ resource: REGISTRIES_FOR_SELECT_RESOURCE, ids: idsOf(draft.registry_id) })
  const campaigns = useForSelectLabels({ resource: CAMPAIGNS_FOR_SELECT_RESOURCE, ids: idsOf(draft.campaign_id) })
  const sites = useForSelectLabels({
    resource: OPERATIONAL_SITES_FOR_SELECT_RESOURCE,
    ids: idsOf(draft.operational_site_id),
  })
  const sources = useForSelectLabels({ resource: SOURCES_FOR_SELECT_RESOURCE, ids: idsOf(draft.source_id) })
  const users = useForSelectLabels({ resource: USERS_FOR_SELECT_RESOURCE, ids: idsOf(draft.operator_id) })
  const products = useForSelectLabels({ resource: PRODUCTS_FOR_SELECT_RESOURCE, ids: productIds })

  const campaignId = draft.campaign_id
  const campaign = campaignId != null ? campaigns.get(campaignId) : undefined

  return {
    registry: draft.registry_id != null ? { id: draft.registry_id, name: nameOf(registries, draft.registry_id) } : null,
    campaign:
      campaignId != null
        ? { id: campaignId, code: campaign?.subtitle ?? '', name: campaign?.label ?? PENDING_LABEL }
        : null,
    operational_site:
      draft.operational_site_id != null
        ? { id: draft.operational_site_id, label: nameOf(sites, draft.operational_site_id) }
        : null,
    source: draft.source_id != null ? { id: draft.source_id, name: nameOf(sources, draft.source_id) } : null,
    operator: draft.operator_id != null ? { id: draft.operator_id, name: nameOf(users, draft.operator_id) } : null,
    notes: draft.notes ?? null,
    extra_fields: entriesToRecord(
      (draft.extra_fields ?? []).map((entry) => ({ key: entry?.key ?? '', value: entry?.value ?? '' })),
    ),
    products_of_interest: productIds.map((id) => productOf(id, products.get(id))),
  }
}
