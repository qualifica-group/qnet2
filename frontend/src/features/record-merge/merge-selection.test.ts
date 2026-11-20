import { describe, expect, it } from 'vitest'
import { PREVIEW_FIXTURE } from '@/features/record-merge/merge-fixtures'
import {
  buildMergePayload,
  defaultChoice,
  duplicateItems,
  fieldDiffers,
  isAlreadyPresent,
  movingLinks,
  toggleDiscarded,
} from '@/features/record-merge/merge-selection'

const field = (key: string) => {
  const found = PREVIEW_FIXTURE.fields.find((entry) => entry.key === key)
  if (!found) {
    throw new Error(`missing fixture field ${key}`)
  }
  return found
}

describe('merge defaults (spec 0394 D-4)', () => {
  it('keeps the survivor value when it is not empty', () => {
    expect(defaultChoice(field('company_name'), 1)).toBe(1)
  })

  it('falls back to the first non-empty duplicate when the survivor value is empty', () => {
    expect(defaultChoice(field('sdi_code'), 1)).toBe(2)
  })

  it('recomputes when the survivor changes', () => {
    expect(defaultChoice(field('company_name'), 2)).toBe(2)
    expect(defaultChoice(field('sdi_code'), 2)).toBe(2)
  })

  it('treats false as a value, not as empty', () => {
    expect(defaultChoice(field('is_supplier'), 2)).toBe(2)
  })

  it('flags only the fields whose values differ', () => {
    expect(fieldDiffers(field('vat_number'))).toBe(false)
    expect(fieldDiffers(field('company_name'))).toBe(true)
  })
})

describe('buildMergePayload', () => {
  it('sends only the fields not taken from the survivor, and no discard by default', () => {
    expect(buildMergePayload(PREVIEW_FIXTURE, 1, {}, {})).toEqual({
      survivor_id: 1,
      merged_ids: [2],
      fields: { sdi_code: 2 },
      discard: {},
    })
  })

  it('honours overrides, including combine', () => {
    const payload = buildMergePayload(PREVIEW_FIXTURE, 1, { company_name: 2, general_notes: 'combine' }, {})
    expect(payload.fields).toEqual({ company_name: 2, general_notes: 'combine', sdi_code: 2 })
  })

  it('lists unchecked duplicate items in discard, never the survivor ones nor already-present ones', () => {
    const discarded = toggleDiscarded(toggleDiscarded({}, 'contacts', 11), 'contacts', 12)
    const payload = buildMergePayload(PREVIEW_FIXTURE, 1, {}, discarded)
    expect(payload.discard).toEqual({ contacts: [11] })
  })

  it('swaps the roles when the other record is the survivor', () => {
    const payload = buildMergePayload(PREVIEW_FIXTURE, 2, {}, {})
    expect(payload.survivor_id).toBe(2)
    expect(payload.merged_ids).toEqual([1])
    expect(payload.fields).toEqual({})
  })
})

describe('already present (match_key)', () => {
  const contacts = PREVIEW_FIXTURE.collections[0]

  it('marks a duplicate item whose match_key exists on the chosen survivor', () => {
    expect(isAlreadyPresent(contacts, contacts.items[2], 1)).toBe(true)
    expect(isAlreadyPresent(contacts, contacts.items[1], 1)).toBe(false)
  })

  it('recomputes when the survivor changes', () => {
    expect(isAlreadyPresent(contacts, contacts.items[0], 2)).toBe(true)
  })

  it('never matches a null match_key', () => {
    const noKey = { ...contacts.items[2], match_key: null }
    expect(isAlreadyPresent({ ...contacts, items: [{ ...contacts.items[0], match_key: null }, noKey] }, noKey, 1)).toBe(false)
  })
})

describe('collections', () => {
  it('shows only the items of the non-survivor records', () => {
    const items = duplicateItems(PREVIEW_FIXTURE.collections[0], 1)
    expect(items.map((item) => item.id)).toEqual([11, 12])
    expect(duplicateItems(PREVIEW_FIXTURE.collections[0], 2).map((item) => item.id)).toEqual([5])
  })

  it('toggling twice restores the item', () => {
    const once = toggleDiscarded({}, 'contacts', 11)
    expect(toggleDiscarded(once, 'contacts', 11).contacts).toEqual([])
  })
})

describe('links that move to the survivor (spec 0394 linked.counts)', () => {
  it('counts and samples only the duplicates, whichever survivor is chosen', () => {
    const [fromTwo] = movingLinks(PREVIEW_FIXTURE.linked, 1)
    expect(fromTwo.count).toBe(3)
    expect(fromTwo.items.map((item) => item.id)).toEqual([100, 101])

    const [fromOne] = movingLinks(PREVIEW_FIXTURE.linked, 2)
    expect(fromOne.count).toBe(1)
    expect(fromOne.items.map((item) => item.id)).toEqual([99])
  })

  it('drops a key when only the survivor has links of that kind', () => {
    const onlySurvivor = [{ key: 'leads', count: 2, counts: [{ record_id: 1, count: 2 }], items: [] }]
    expect(movingLinks(onlySurvivor, 1)).toEqual([])
  })
})
