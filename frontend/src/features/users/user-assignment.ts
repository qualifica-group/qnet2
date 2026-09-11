import { useResourcePermissions } from '@/features/authorization/permissions'
import type { ProductLineRow } from '@/features/product-lines/types'

/**
 * The user's assignment configuration, read exactly the way the server reads
 * it when it picks who may receive a record (`AssignmentCandidates`): the pool
 * is the operators of the record's Sede INTERSECTED with those competent for
 * the categories it demands.
 *
 * Both halves are blocking, and that is the whole point of surfacing them
 * together on one screen:
 *  - Sede: physical and remote count the SAME (spec 0103 D-1, one pivot), and
 *    a person with no Sede at all is in no pool;
 *  - competence: since spec 0111 rev.2 (D-9) an empty competence set is NOT a
 *    jolly any more — it covers nothing.
 *
 * A pure projection of what the form already holds: no fetch, no server call.
 * It cannot say WHICH offers will reach the person (that depends on each
 * record's own Sede and category), only whether the configuration lets any
 * reach them at all.
 */

/** Why the person is in no assignment pool at all. Empty when they are assignable. */
export type AssignmentBlocker = 'competence' | 'site'

export interface AssignmentSummary {
  /** COMPLETE `funzione aziendale -> categoria prodotto` rows: a half-filled row covers nothing. */
  competenceCount: number
  /** 0 or 1 — the physical site is at most one (spec 0103 D-3). */
  physicalSiteCount: number
  remoteSiteCount: number
  /** Physical + remote: the membership the server reads makes no distinction. */
  siteCount: number
  blockers: AssignmentBlocker[]
  assignable: boolean
}

interface AssignmentInput {
  competenceRows: readonly ProductLineRow[]
  primarySiteId: number | null
  remoteSiteIds: readonly number[]
}

export function summarizeAssignment({
  competenceRows,
  primarySiteId,
  remoteSiteIds,
}: AssignmentInput): AssignmentSummary {
  const competenceCount = competenceRows.filter(
    (row) => row.business_function_id !== null && row.product_category_id !== null,
  ).length
  const physicalSiteCount = primarySiteId !== null ? 1 : 0
  const remoteSiteCount = remoteSiteIds.length
  const siteCount = physicalSiteCount + remoteSiteCount

  const blockers: AssignmentBlocker[] = []
  if (competenceCount === 0) {
    blockers.push('competence')
  }
  if (siteCount === 0) {
    blockers.push('site')
  }

  return {
    competenceCount,
    physicalSiteCount,
    remoteSiteCount,
    siteCount,
    blockers,
    assignable: blockers.length === 0,
  }
}

/** Per-field visibility of the three controls the assignment configuration is made of. */
export interface AssignmentFieldsVisibility {
  competence: boolean
  primarySite: boolean
  remoteSites: boolean
  /** False = the actor sees none of them, so nothing about assignment may be shown at all. */
  any: boolean
}

/**
 * The `MetaField` gate of the assignment controls (spec 0004), read ONCE and
 * shared by the three surfaces that must agree on it: the section itself, the
 * side-column recap and the identity bar's verdict pill.
 *
 * Without this the recap would name a field the role is not allowed to see —
 * a label is already a leak — and the pill would judge a configuration the
 * actor cannot even read.
 */
export function useAssignmentFieldsVisibility(): AssignmentFieldsVisibility {
  const { field } = useResourcePermissions()

  const competence = field('employment.product_lines').visible
  const primarySite = field('employment.primary_operational_site_id').visible
  const remoteSites = field('employment.remote_operational_site_ids').visible

  return {
    competence,
    primarySite,
    remoteSites,
    any: competence || primarySite || remoteSites,
  }
}
