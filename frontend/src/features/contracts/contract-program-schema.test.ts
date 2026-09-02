import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildContractProgramSchema, contractProgramDefaultValues } from '@/features/contracts/contract-program-schema'

describe('buildContractProgramSchema (spec 0095 D-11, spec 0096 D-5)', () => {
  const schema = buildContractProgramSchema(i18n.t)
  /** The fields spec 0096 added to the dialog; spread into every case below. */
  const REQUIRED = { start_date: '2026-03-01', supervisor_ids: [21] }

  it('requires a title', () => {
    expect(schema.safeParse({ ...REQUIRED, title: '', type: 'processing', quote_line_ids: [1] }).success).toBe(false)
    expect(schema.safeParse({ ...REQUIRED, title: 'Installazione', type: 'processing', quote_line_ids: [1] }).success).toBe(true)
  })

  it('rejects a title over 191 characters', () => {
    expect(
      schema.safeParse({ ...REQUIRED, title: 'a'.repeat(192), type: 'processing', quote_line_ids: [1] }).success,
    ).toBe(false)
  })

  it('requires a valid type', () => {
    expect(
      schema.safeParse({ ...REQUIRED, title: 'Installazione', type: 'bogus', quote_line_ids: [1] }).success,
    ).toBe(false)
  })

  it('requires a start date and at least one responsabile (spec 0096, AC-060)', () => {
    expect(
      schema.safeParse({ title: 'Installazione', type: 'processing', quote_line_ids: [1], supervisor_ids: [21] })
        .success,
    ).toBe(false)
    expect(
      schema.safeParse({
        title: 'Installazione',
        type: 'processing',
        quote_line_ids: [1],
        start_date: '2026-03-01',
        supervisor_ids: [],
      }).success,
    ).toBe(false)
  })

  it('requires at least one selected line (AC-061)', () => {
    expect(
      schema.safeParse({ ...REQUIRED, title: 'Installazione', type: 'processing', quote_line_ids: [] }).success,
    ).toBe(false)
    expect(
      schema.safeParse({ ...REQUIRED, title: 'Installazione', type: 'processing', quote_line_ids: [1, 2] }).success,
    ).toBe(true)
  })
})

describe('contractProgramDefaultValues', () => {
  it('defaults type to "processing" and no selected lines', () => {
    expect(contractProgramDefaultValues()).toEqual({
      title: '',
      type: 'processing',
      start_date: '',
      supervisor_ids: [],
      quote_line_ids: [],
    })
  })
})
