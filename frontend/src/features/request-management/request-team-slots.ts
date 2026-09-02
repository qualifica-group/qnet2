import { OPERATOR_MANAGER_POSITION } from '@/features/request-management/types'
import type { ForSelectItem } from '@/features/for-select/types'
import type { UserForSelectItem } from '@/features/users/for-select-api'

/**
 * The Sede <-> Operatore reciprocal link (user directives 2026-07-23 and
 * 2026-07-31), rebound to the team editor by spec 0097 D-4. The rule did not
 * change when the single "Operatore" picker did: only ONE slot, the operator's,
 * is scoped to the Sede operativa, and only a pick made on that slot may
 * hydrate it back.
 *
 * This file says what the rule IS. Where it is wired to a form —
 * `previousSiteIdRef`, the auto-filled Sede, the two handlers — is
 * `use-request-site-operator-link.ts`, called once by each screen that owns
 * the form (spec 0097 rev-2 D-7: the Sede and the slot it scopes ended up in
 * two different sections, so neither of them may own the link).
 */

/** The `for-select` filter of one slot: the Sede for the operator's, nothing for every other position. */
export function operatorSlotParams(
  position: number,
  siteId: number | null,
): Record<string, string | number> | undefined {
  return position === OPERATOR_MANAGER_POSITION && siteId !== null
    ? { operational_site_id: siteId }
    : undefined
}

/**
 * The Sede the picked user belongs to, read from the item's own `meta` (no
 * extra fetch). `null` — nothing to hydrate — when the pick was made on any
 * other slot, when the slot was cleared, or when that user has no Sede.
 */
export function operatorSlotSite(
  position: number,
  item: ForSelectItem | null,
): { id: number; label: string } | null {
  if (position !== OPERATOR_MANAGER_POSITION) {
    return null
  }

  const meta = (item as UserForSelectItem | null)?.meta
  if (meta?.operational_site_id == null) {
    return null
  }

  return { id: meta.operational_site_id, label: meta.operational_site_label ?? `#${meta.operational_site_id}` }
}

/**
 * Empties ONE position of a gap-aware slot array, leaving every other slot
 * exactly as it is: a Sede change invalidates the operator it scopes, never
 * the rest of the team (which is not bound to a site at all).
 */
export function clearManagerSlot(slots: (number | null)[], position: number): (number | null)[] {
  return slots.map((slot, index) => (index + 1 === position ? null : slot))
}
