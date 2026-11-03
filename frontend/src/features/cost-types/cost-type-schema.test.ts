import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildCostTypeLedgerSchema, buildCostTypeSchema } from '@/features/cost-types/cost-type-schema'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const VALID = { name: 'Collaboratori', description: null, nature: 'direct', color: 'slate', is_active: true }

describe('buildCostTypeSchema (specs 0210, 0249)', () => {
  const schema = () => buildCostTypeSchema(i18n.t)

  it('accepts a valid payload', () => {
    expect(schema().safeParse(VALID).success).toBe(true)
  })

  it('rejects an empty name', () => {
    expect(schema().safeParse({ ...VALID, name: '' }).success).toBe(false)
  })

  it('rejects a name over 100 characters', () => {
    expect(schema().safeParse({ ...VALID, name: 'a'.repeat(101) }).success).toBe(false)
  })

  it('rejects a description over 500 characters', () => {
    expect(schema().safeParse({ ...VALID, description: 'a'.repeat(501) }).success).toBe(false)
  })

  it.each(['direct', 'indirect', 'general'])('accepts the nature %s', (nature) => {
    expect(schema().safeParse({ ...VALID, nature }).success).toBe(true)
  })

  it.each(['', 'other'])('rejects an unknown nature (%j)', (nature) => {
    expect(schema().safeParse({ ...VALID, nature }).success).toBe(false)
  })

  it.each(['', 'not-a-token'])('rejects a missing or unknown color (%j)', (color) => {
    expect(schema().safeParse({ ...VALID, color }).success).toBe(false)
  })
})

describe('buildCostTypeLedgerSchema (spec 0348)', () => {
  const ledger = () => buildCostTypeLedgerSchema(i18n.t)
  const LEDGER = { ...VALID, chart_account_id: 10, code: '61.03.001' }

  it('accepts a cost type with account and code', () => {
    expect(ledger().safeParse(LEDGER).success).toBe(true)
  })

  it('requires the account and the code', () => {
    expect(ledger().safeParse({ ...LEDGER, chart_account_id: undefined }).success).toBe(false)
    expect(ledger().safeParse({ ...LEDGER, code: '' }).success).toBe(false)
  })

  it.each(['61 03', 'a'.repeat(21), 'caffè'])('rejects the invalid code %j', (code) => {
    expect(ledger().safeParse({ ...LEDGER, code }).success).toBe(false)
  })

  it('leaves the page schema untouched: no account or code required', () => {
    expect(buildCostTypeSchema(i18n.t).safeParse(VALID).success).toBe(true)
  })
})
