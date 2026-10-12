import { useMemo } from 'react'
import { useWatch, type Control } from 'react-hook-form'
import type { ForSelectItem } from '@/features/for-select/types'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { useGeoNames } from '@/features/geo/use-geo-names'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { PROJECT_STATUSES_FOR_SELECT_RESOURCE } from '@/features/pipeline-statuses/for-select-api'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import { categoryPathLabel } from '@/features/product-lines/category-path-label'
import { PROJECTS_FOR_SELECT_RESOURCE } from '@/features/projects/for-select-api'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import type { CampaignRecordValues } from '@/features/campaigns/campaign-record'
import type { CampaignFormValues } from '@/features/campaigns/use-campaign-form'

/** Shown while a label resolves: never the bare id. */
const PENDING_LABEL = '…'

/** Stable empty defaults: a fresh value per render would break the label hooks' memo. */
const NO_IDS: number[] = []
const NO_TREE: ProductCategoryTreeNode[] = []

function idsOf(id: number | null | undefined): number[] {
  return id != null ? [id] : NO_IDS
}

function refOf(labels: Map<number, ForSelectItem>, id: number | null | undefined) {
  return id != null ? { id, name: labels.get(id)?.label ?? PENDING_LABEL } : null
}

/**
 * The create draft as the record's closed rows read it (spec 0221): the form
 * holds ids only, so every relation is named through `useForSelectLabels` —
 * the very cache the pickers fill for their own trigger — the classification
 * rows through the cached category tree and the geo levels through
 * `useGeoNames`.
 */
export function useCampaignDraftValues(control: Control<CampaignFormValues>): CampaignRecordValues {
  const draft = useWatch({ control })
  const projects = useForSelectLabels({ resource: PROJECTS_FOR_SELECT_RESOURCE, ids: idsOf(draft.project_id) })
  const statuses = useForSelectLabels({
    resource: PROJECT_STATUSES_FOR_SELECT_RESOURCE,
    ids: idsOf(draft.pipeline_status_id),
  })
  const partners = useForSelectLabels({ resource: REFERENTS_FOR_SELECT_RESOURCE, ids: idsOf(draft.partner_id) })
  const sites = useForSelectLabels({
    resource: OPERATIONAL_SITES_FOR_SELECT_RESOURCE,
    ids: idsOf(draft.operational_site_id),
  })
  const sources = useForSelectLabels({ resource: SOURCES_FOR_SELECT_RESOURCE, ids: idsOf(draft.source_id) })
  const tree = useProductCategoryTree().data ?? NO_TREE
  const geo = useGeoNames({
    country_id: draft.country_id ?? null,
    state_id: draft.state_id ?? null,
    province_id: draft.province_id ?? null,
    city_id: draft.city_id ?? null,
  })
  const categoryIds = useMemo(
    () =>
      (draft.product_lines ?? [])
        .map((row) => row?.product_category_id ?? null)
        .filter((id): id is number => id !== null),
    [draft.product_lines],
  )
  const project = refOf(projects, draft.project_id)
  const site = refOf(sites, draft.operational_site_id)

  return {
    code: draft.code ?? '',
    name: draft.name ?? '',
    description: draft.description ?? null,
    project: project ? { id: project.id, label: project.name } : null,
    partner: refOf(partners, draft.partner_id),
    operational_site: site ? { id: site.id, label: site.name } : null,
    source: refOf(sources, draft.source_id),
    pipeline_status: refOf(statuses, draft.pipeline_status_id),
    product_lines: null,
    product_line_labels: categoryIds.map((id) => categoryPathLabel(tree, id)),
    country: geo.country,
    state: geo.state,
    province: geo.province,
    city: geo.city,
    geo_locked_levels: (draft.geo_locked_levels ?? []).filter((level) => level !== undefined),
    start_date: draft.start_date || null,
    end_date: draft.end_date || null,
    total_budget: draft.total_budget ?? null,
    target_lead: draft.target_lead ?? null,
    custom_fields: (draft.custom_fields ?? {}) as CampaignRecordValues['custom_fields'],
  }
}
