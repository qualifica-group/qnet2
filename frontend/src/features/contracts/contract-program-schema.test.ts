import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildContractProgramSchema, contractProgramDefaultValues } from '@/features/contracts/contract-program-schema'

describe('buildContractProgramSchema (spec 0095 D-11)', () => {
  const schema = buildContractProgramSchema(i18n.t)

  it('requires a title', () => {
    expect(schema.safeParse({ title: '', type: 'processing', quote_line_ids: [1] }).success).toBe(false)
    expect(schema.safeParse({ title: 'Installazione', type: 'processing', quote_line_ids: [1] }).success).toBe(true)
  })

  it('rejects a title over 191 characters', () => {
    expect(
      schema.safeParse({ title: 'a'.repeat(192), type: 'processing', quote_line_ids: [1] }).success,
    ).toBe(false)
  })

  it('requires a valid type', () => {
    expect(
      schema.safeParse({ title: 'Installazione', type: 'bogus', quote_line_ids: [1] }).success,
    ).toBe(false)
  })

  it('requires at least one selected line (AC-061)', () => {
    expect(
      schema.safeParse({ title: 'Installazione', type: 'processing', quote_line_ids: [] }).success,
    ).toBe(false)
    expect(
      schema.safeParse({ title: 'Installazione', type: 'processing', quote_line_ids: [1, 2] }).success,
    ).toBe(true)
  })
})

describe('contractProgramDefaultValues', () => {
  it('defaults type to "processing" and no selected lines', () => {
    expect(contractProgramDefaultValues()).toEqual({ title: '', type: 'processing', quote_line_ids: [] })
  })
})
