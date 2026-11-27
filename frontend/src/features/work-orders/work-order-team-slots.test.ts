import { describe, expect, it } from 'vitest'
import { MAX_MANAGER_SLOTS } from '@/components/form/manager-slots-limits'
import {
  clearParticipantSlot,
  hasFreeParticipantSlot,
  moveParticipantSlot,
  toggleParticipantId,
  toggleSupervisorId,
} from '@/features/work-orders/work-order-team-slots'

const FULL = Array.from({ length: MAX_MANAGER_SLOTS }, (_, index) => index + 1)

describe('team popup edits (user request 2026-11-27)', () => {
  it('adds a responsabile or takes an existing one out', () => {
    expect(toggleSupervisorId([1, 2], 3)).toEqual([1, 2, 3])
    expect(toggleSupervisorId([1, 2], 1)).toEqual([2])
  })

  it('puts a new partecipante in the first gap, else in a new last slot', () => {
    expect(toggleParticipantId([7, null, 9], 5)).toEqual([7, 5, 9])
    expect(toggleParticipantId([7, 9], 5)).toEqual([7, 9, 5])
    expect(toggleParticipantId([], 5)).toEqual([5])
  })

  it('frees the slots of a partecipante picked again, keeping the others numbered', () => {
    expect(toggleParticipantId([7, 5, 9], 5)).toEqual([7, null, 9])
    expect(toggleParticipantId([7, 5], 5)).toEqual([7])
  })

  it('refuses a partecipante when every slot is taken', () => {
    expect(toggleParticipantId(FULL, 99)).toBeNull()
    expect(hasFreeParticipantSlot(FULL)).toBe(false)
    expect(hasFreeParticipantSlot([...FULL.slice(1), null])).toBe(true)
  })

  it('clears one slot as a gap and drops trailing gaps', () => {
    expect(clearParticipantSlot([7, 5, 9], 2)).toEqual([7, null, 9])
    expect(clearParticipantSlot([7, null, 9], 3)).toEqual([7])
  })

  it('swaps a slot with its neighbour, never past either end', () => {
    expect(moveParticipantSlot([7, 5, 9], 1, 1)).toEqual([5, 7, 9])
    expect(moveParticipantSlot([7, null, 9], 3, -1)).toEqual([7, 9])
    expect(moveParticipantSlot([7, 5], 1, -1)).toEqual([7, 5])
    expect(moveParticipantSlot([7, 5], 2, 1)).toEqual([7, 5])
  })
})
