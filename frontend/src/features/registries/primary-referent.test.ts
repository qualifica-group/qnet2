import { describe, expect, it } from 'vitest'
import { nextPrimaryReferentId, nextSecondaryReferentId } from '@/features/registries/primary-referent'

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

describe('nextSecondaryReferentId (spec 0408 D-4, D-9)', () => {
  it('keeps the secondary referent while listed and not the main one', () => {
    expect(nextSecondaryReferentId(8, 7, [7, 8, 9], [7, 8])).toBe(8)
  })

  it('clears it when it leaves the list or becomes the main one, with nothing added', () => {
    expect(nextSecondaryReferentId(8, 7, [7], [7, 8])).toBeNull()
    expect(nextSecondaryReferentId(8, 8, [7, 8], [7, 8])).toBeNull()
    expect(nextSecondaryReferentId(null, 7, [7, 8], [7, 8])).toBeNull()
  })

  it('takes the referent just added when it is empty', () => {
    expect(nextSecondaryReferentId(null, 7, [7, 8], [7])).toBe(8)
    expect(nextSecondaryReferentId(null, 7, [7, 8, 9], [7, 8])).toBe(9)
  })

  it('stays empty when the only referent added becomes the main one', () => {
    expect(nextSecondaryReferentId(null, 7, [7], [])).toBeNull()
  })
})
