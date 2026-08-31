import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

/**
 * Order-independent, duplicate-safe comparison of two id collections. Shared
 * by every sparse-PATCH builder whose payload key is a full-replace SET (the
 * opportunity's `products_of_interest`/`rewards`, the offer's `rewards`): the
 * key must travel only when the SET actually changed, and the row order those
 * chips render in carries no meaning.
 */
export function sameIdSet(a: number[], b: number[]): boolean {
  if (a.length !== b.length) {
    return false
  }
  const setB = new Set(b)
  return a.every((id) => setB.has(id))
}

/** The minimal shape `managerSlotsFromRefs` needs: an id and its 1-based "G.A. n" position. */
export interface ManagerSlotRef {
  id: number
  position: number
}

/**
 * Rebuilds the positional, gap-aware "G.A. n" slot array (`(number | null)[]`,
 * index+1 = position) from a sparse `{id, position}` list — the shape both
 * `OpportunityDetail.managers` and `QuoteDetail.managers` expose (there is no
 * dedicated `manager_slots` field on either detail response). Shared here,
 * not duplicated per module: moved out once a second real call site appeared
 * (spec 0087), the same reason `sameIdSet` above already lives here instead
 * of inside `opportunity-form-payload.ts`.
 */
export function managerSlotsFromRefs(managers: ManagerSlotRef[]): (number | null)[] {
  const highestPosition = managers.reduce((max, manager) => Math.max(max, manager.position), 0)
  const slots: (number | null)[] = new Array(highestPosition).fill(null)
  managers.forEach((manager) => {
    slots[manager.position - 1] = manager.id
  })
  return slots
}

/** Positional (order- and gap-sensitive) comparison of two G.A. slot arrays, same sharing rationale as `managerSlotsFromRefs`. */
export function sameManagerSlots(a: (number | null)[], b: (number | null)[]): boolean {
  const length = Math.max(a.length, b.length)
  for (let index = 0; index < length; index += 1) {
    if ((a[index] ?? null) !== (b[index] ?? null)) {
      return false
    }
  }
  return true
}

/** Shallow, key-for-key equality of two resolved G.A. label maps. */
function sameManagerLabels(a: Record<string, string>, b: Record<string, string>): boolean {
  const aKeys = Object.keys(a)
  const bKeys = Object.keys(b)
  return aKeys.length === bKeys.length && aKeys.every((key) => a[key] === b[key])
}

/**
 * The client-side mirror of the backend's G.A. label univocity rule (spec
 * 0080 for Opportunita', spec 0087 D-8 for Offerta), shared by every entity
 * that resolves labels from its own product categories — one copy on
 * purpose, same rationale as `managerSlotsFromRefs` above: the rule decides
 * what an operator SEES named on a field, and two drifting copies would name
 * the same slot differently on two screens. Zero categories -> `{}` (default
 * denominations); one -> its own effective labels; several -> the shared
 * result ONLY when every one of them resolves to the IDENTICAL set (compared
 * on the resolved labels, not the category id — two different categories
 * defining the same labels is not a conflict), otherwise `{}`.
 */
export function resolveManagerLabels(perCategory: Record<string, string>[]): Record<string, string> {
  if (perCategory.length === 0) {
    return {}
  }
  const [first, ...rest] = perCategory
  return rest.every((labels) => sameManagerLabels(labels, first)) ? first : {}
}

/**
 * Pads a G.A. slot array up to `size` empty slots, leaving it untouched when
 * it is already that long or longer.
 *
 * Shared because every "prefill the team from a picked entity" path needs it
 * for the same reason: the source may have fewer managers than the form shows
 * cards, and an operator must still see a full, editable set of slots rather
 * than a truncated one. `size` is a parameter, not a constant, because each
 * feature owns its own `DEFAULT_MANAGER_SLOTS`.
 */
export function padManagerSlots(slots: (number | null)[], size: number): (number | null)[] {
  return slots.length >= size
    ? slots
    : [...slots, ...Array.from({ length: size - slots.length }, () => null)]
}
