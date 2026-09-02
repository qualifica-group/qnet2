import { apiClient } from '@/api/client'
import { FOR_SELECT_PAGE_SIZE } from '@/features/for-select/api'
import type { ForSelectItem, ForSelectParams, PaginatedResponse } from '@/features/for-select/types'
import type { ProductLineRelationRef } from '@/features/product-lines/types'

/** Resource segment for the projects for-select endpoint (spec 0023). */
export const PROJECTS_FOR_SELECT_RESOURCE = 'projects'

/** A relation's `{id, label}` projection inside `ProjectForSelectItem.meta`. */
export interface ProjectForSelectRelation {
  id: number
  label: string
}

/**
 * A geo level's `{id, name}` projection inside `meta.geo` (spec 0027 D-5) —
 * distinct from `ProjectForSelectRelation`'s `{id, label}`: this block feeds
 * `GeoSelect` directly, which already speaks `name`.
 */
export interface ProjectForSelectGeoRelation {
  id: number
  name: string
}

/**
 * Which geo levels the linked project already fills (spec 0027 D-5): tells
 * the Campaign form which of the 4 cascade levels to lock/prefill when a
 * project is picked.
 */
export interface ProjectForSelectGeo {
  country: ProjectForSelectGeoRelation | null
  state: ProjectForSelectGeoRelation | null
  province: ProjectForSelectGeoRelation | null
  city: ProjectForSelectGeoRelation | null
}

/**
 * A `product_lines` row as exposed by the project's `for-select` `meta`
 * block (spec 0094): unlike the confirmed `ProductLine` (persisted row, with
 * its own `id`), this one is a plain pair — the prefill only ever needs the
 * two relation ids/names, never a row identity of its own.
 */
export interface ProjectForSelectProductLine {
  business_function: ProductLineRelationRef
  product_category: ProductLineRelationRef
}

/**
 * The `meta` block carried by every `/projects/for-select` item (spec 0023):
 * feeds the Campaign form's default-population when a Project is linked
 * (AC-042) — no extra request, the picker's own response already carries it.
 * `total_budget`/`allocated_budget`/`remaining_budget` are decimal columns
 * cast `decimal:2`, serialized as numeric strings. `geo` (spec 0027 D-5) is
 * the project's own geo cascade, used to lock/prefill the campaign form.
 * `product_lines` (spec 0094) replaces the former single
 * `business_function`/`product_category` pair.
 */
export interface ProjectForSelectMeta {
  partner: ProjectForSelectRelation | null
  pipeline_status: ProjectForSelectRelation
  state: ProjectForSelectRelation | null
  product_lines: ProjectForSelectProductLine[]
  total_budget: string | null
  allocated_budget: string
  remaining_budget: string | null
  geo: ProjectForSelectGeo
  /** The project's own Sede, inherited as a prefill (never a lock) by a campaign linking this project. */
  operational_site: ProjectForSelectRelation | null
}

/** A single project option as returned by `GET /api/projects/for-select`, label = "PRJ-0001 — Name". */
export interface ProjectForSelectItem extends ForSelectItem {
  meta: ProjectForSelectMeta
}

/**
 * Fetches a page of project options (with their `meta` default-population
 * block) from `GET /api/projects/for-select`. NOT a thin call to the generic
 * `fetchForSelect` (typed to the meta-less `ForSelectItem`): this is the same
 * envelope, typed with the richer `ProjectForSelectItem` shape the Campaign
 * form needs.
 */
export async function fetchProjectsForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ProjectForSelectItem>> {
  const { search, offset = 0, limit = FOR_SELECT_PAGE_SIZE, ids } = params
  const { data } = await apiClient.get<PaginatedResponse<ProjectForSelectItem>>(
    `/${PROJECTS_FOR_SELECT_RESOURCE}/for-select`,
    {
      params: {
        offset,
        limit,
        ...(search ? { search } : {}),
        ...(ids && ids.length > 0 ? { ids } : {}),
      },
      paramsSerializer: { indexes: true },
    },
  )
  return data
}
