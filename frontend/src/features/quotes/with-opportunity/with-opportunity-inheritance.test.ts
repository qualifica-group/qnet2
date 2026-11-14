import { describe, expect, it } from 'vitest'
import { alignInheritedFields, type InheritedFields } from '@/features/quotes/with-opportunity/with-opportunity-inheritance'

const STEP_ONE: InheritedFields = {
  commercial_id: 3,
  reporter_id: 4,
  supervisor_id: 9,
  operational_site_id: 6,
  manager_slots: [11, null, null, null],
}

const BLANK = {
  commercial_id: null,
  reporter_id: null,
  supervisor_id: null,
  operational_site_id: null,
  manager_slots: [null, null, null, null],
}

/** Spec 0396 D-5: the offer follows step 1 on the fields it did not touch. */
describe('alignInheritedFields', () => {
  it('fills a blank offer with everything step 1 holds', () => {
    expect(alignInheritedFields(BLANK, null, STEP_ONE)).toEqual(STEP_ONE)
  })

  it('leaves a field typed by hand alone and realigns the others', () => {
    const current = { ...STEP_ONE, commercial_id: 77 }
    const next = { ...STEP_ONE, commercial_id: 30, reporter_id: 40 }

    expect(alignInheritedFields(current, STEP_ONE, next)).toEqual({ reporter_id: 40 })
  })

  it('treats a slot team edited by hand as touched', () => {
    const current = { ...STEP_ONE, manager_slots: [11, 12, null, null] }

    expect(alignInheritedFields(current, STEP_ONE, { ...STEP_ONE, manager_slots: [13, null, null, null] })).toEqual({})
  })

  it('writes nothing when nothing changed', () => {
    expect(alignInheritedFields(STEP_ONE, STEP_ONE, STEP_ONE)).toEqual({})
  })
})
