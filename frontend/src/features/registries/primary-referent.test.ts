import { describe, expect, it } from 'vitest'
import { nextPrimaryReferentId } from '@/features/registries/primary-referent'

describe('nextPrimaryReferentId (spec 0330 D-9)', () => {
  it('takes the first referent when the first one is added', () => {
    expect(nextPrimaryReferentId(null, [7])).toBe(7)
  })

  it('keeps the main referent while it is still listed', () => {
    expect(nextPrimaryReferentId(8, [7, 8, 9])).toBe(8)
  })

  it('moves to the first remaining referent when the main one is removed', () => {
    expect(nextPrimaryReferentId(7, [8, 9])).toBe(8)
  })

  it('is empty without referents', () => {
    expect(nextPrimaryReferentId(7, [])).toBeNull()
  })
})
