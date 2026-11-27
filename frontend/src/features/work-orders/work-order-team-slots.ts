import { MAX_MANAGER_SLOTS } from '@/components/form/manager-slots-limits'

/**
 * The pure edits of the "Responsabili e Partecipanti" popup, on the wire
 * shapes the PATCH takes: `supervisor_ids`, an unordered set, and
 * `participant_slots`, the gap-aware slots where index + 1 is the
 * "Partecipante n" denomination (spec 0096). A cleared slot stays a gap so
 * the others keep their number; trailing gaps are dropped.
 */

export type ParticipantSlots = (number | null)[]

function trimTrailingGaps(slots: ParticipantSlots): ParticipantSlots {
  let end = slots.length
  while (end > 0 && slots[end - 1] === null) {
    end -= 1
  }
  return slots.slice(0, end)
}

/** Adds the person, or takes them out when they are already a Responsabile. */
export function toggleSupervisorId(ids: number[], id: number): number[] {
  return ids.includes(id) ? ids.filter((current) => current !== id) : [...ids, id]
}

/**
 * Adds the person in the first free slot (a gap, else a new last one), or
 * frees every slot they hold when they are already a Partecipante. `null`
 * when every slot is taken.
 */
export function toggleParticipantId(slots: ParticipantSlots, id: number): ParticipantSlots | null {
  if (slots.includes(id)) {
    return trimTrailingGaps(slots.map((current) => (current === id ? null : current)))
  }
  const gap = slots.indexOf(null)
  if (gap !== -1) {
    return slots.map((current, index) => (index === gap ? id : current))
  }
  return slots.length < MAX_MANAGER_SLOTS ? [...slots, id] : null
}

/** Frees the slot at the 1-based `position`; the others keep their number. */
export function clearParticipantSlot(slots: ParticipantSlots, position: number): ParticipantSlots {
  return trimTrailingGaps(slots.map((current, index) => (index === position - 1 ? null : current)))
}

/** Swaps the slot at the 1-based `position` with its neighbour above (-1) or below (+1). */
export function moveParticipantSlot(slots: ParticipantSlots, position: number, delta: -1 | 1): ParticipantSlots {
  const from = position - 1
  const to = from + delta
  if (to < 0 || to >= slots.length) {
    return slots
  }
  const next = [...slots]
  ;[next[from], next[to]] = [next[to], next[from]]
  return trimTrailingGaps(next)
}

/** Whether one more person fits: a gap to fill or room for a new slot. */
export function hasFreeParticipantSlot(slots: ParticipantSlots): boolean {
  return slots.includes(null) || slots.length < MAX_MANAGER_SLOTS
}
