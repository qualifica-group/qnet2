import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { ForSelectItem } from '@/features/for-select/types'

/** Resource segment for the opportunities for-select endpoint (ADR 0011). */
export const OPPORTUNITIES_FOR_SELECT_RESOURCE = 'opportunities'

/**
 * The `meta` block carried by every `/opportunities/for-select` item: the
 * three commercial roles a new Offerta snapshots from its Opportunita'
 * (spec 0065 D-3, directive 2026-07-29). Always present, each key null when
 * the opportunity has none — so a picker built on this resource prefills them
 * on selection without a second fetch.
 */
export interface OpportunityForSelectMeta {
  commercial: RelationFieldRef | null
  reporter: RelationFieldRef | null
  supervisor: RelationFieldRef | null
}

/** A single opportunity option as returned by `GET /api/opportunities/for-select`. */
export interface OpportunityForSelectItem extends ForSelectItem {
  meta?: OpportunityForSelectMeta
}
