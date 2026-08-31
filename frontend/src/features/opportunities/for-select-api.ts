import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { ForSelectItem } from '@/features/for-select/types'
import type { OpportunityManagerRef } from '@/features/opportunities/types'

/** Resource segment for the opportunities for-select endpoint (ADR 0011). */
export const OPPORTUNITIES_FOR_SELECT_RESOURCE = 'opportunities'

/**
 * The `meta` block carried by every `/opportunities/for-select` item: what a
 * new Offerta snapshots from its Opportunita' — the three commercial roles
 * (spec 0065 D-3, directive 2026-07-29) plus the sede operativa (directive
 * 2026-07-30). Always present, each key null when the opportunity has none —
 * so a picker built on this resource prefills them on selection without a
 * second fetch.
 *
 * `operational_site` is `{id, label}`, not `{id, name}`: the site has no name
 * column, its identity is its primary address.
 */
export interface OpportunityForSelectMeta {
  commercial: RelationFieldRef | null
  reporter: RelationFieldRef | null
  supervisor: RelationFieldRef | null
  operational_site: { id: number; label: string } | null
  /** The opportunity's own Gestori Account (spec 0087, D-5), ordered by position, `[]` when it has none. */
  managers: OpportunityManagerRef[]
}

/** The three role keys of `OpportunityForSelectMeta` — the `{id, name}` refs a role field can hydrate from. */
export type OpportunityForSelectRoleKey = 'commercial' | 'reporter' | 'supervisor'

/** A single opportunity option as returned by `GET /api/opportunities/for-select`. */
export interface OpportunityForSelectItem extends ForSelectItem {
  meta?: OpportunityForSelectMeta
}
