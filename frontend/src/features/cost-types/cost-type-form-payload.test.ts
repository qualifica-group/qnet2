import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildLedgerCreatePayload,
  buildLedgerUpdatePayload,
  buildUpdatePayload,
} from '@/features/cost-types/cost-type-form-payload'
import type { CostTypeDetail } from '@/features/cost-types/types'

const ORIGINAL: CostTypeDetail = {
  id: 1,
  name: 'Fitti',
  description: 'Rents',
  nature: 'direct',
  color: 'slate',
  sort_order: 2,
  is_active: true,
  code: null,
  chart_account_id: null,
  created_at: '2026-10-12T08:00:00Z',
  updated_at: '2026-10-12T08:00:00Z',
  abilities: { update: true, delete: true },
}

const VALUES = { name: 'Fitti', description: 'Rents', nature: 'direct', color: 'slate', is_active: true } as const

describe('buildCreatePayload (spec 0249)', () => {
  it('builds the full payload, trimming the name and never carrying sort_order', () => {
    const payload = buildCreatePayload({ ...VALUES, name: ' Fitti ', nature: 'general', color: 'blue' })

    expect(payload).toEqual({ name: 'Fitti', description: 'Rents', nature: 'general', color: 'blue', is_active: true })
    expect(payload).not.toHaveProperty('sort_order')
  })

  it('sends a blank description as null', () => {
    expect(buildCreatePayload({ ...VALUES, description: '   ' }).description).toBeNull()
  })
})

describe('buildUpdatePayload (spec 0249)', () => {
  it('is empty when nothing changed', () => {
    expect(buildUpdatePayload(VALUES, ORIGINAL)).toEqual({})
  })

  it('carries only the changed fields', () => {
    expect(buildUpdatePayload({ ...VALUES, nature: 'indirect', color: 'blue' }, ORIGINAL)).toEqual({
      nature: 'indirect',
      color: 'blue',
    })
  })

  it('maps a cleared description to null', () => {
    expect(buildUpdatePayload({ ...VALUES, description: '' }, ORIGINAL)).toEqual({ description: null })
  })
})

describe('ledger payloads (spec 0348)', () => {
  const LEDGER_VALUES = { ...VALUES, chart_account_id: 10, code: ' 61.03.001 ' }
  const PLACED = { ...ORIGINAL, code: '61.03.001', chart_account_id: 10 }

  it('the plain page payloads never carry the two ledger fields', () => {
    expect(buildCreatePayload(VALUES)).not.toHaveProperty('chart_account_id')
    expect(buildCreatePayload(VALUES)).not.toHaveProperty('code')
    expect(buildUpdatePayload(VALUES, ORIGINAL)).toEqual({})
  })

  it('create carries the account and the trimmed code', () => {
    expect(buildLedgerCreatePayload(LEDGER_VALUES)).toMatchObject({ chart_account_id: 10, code: '61.03.001' })
  })

  it('placing an unplaced type sends account and code together', () => {
    expect(buildLedgerUpdatePayload(LEDGER_VALUES, ORIGINAL)).toEqual({ chart_account_id: 10, code: '61.03.001' })
  })

  it('is empty when nothing changed and sends only the code when only it changed', () => {
    expect(buildLedgerUpdatePayload({ ...LEDGER_VALUES, code: '61.03.001' }, PLACED)).toEqual({})
    expect(buildLedgerUpdatePayload({ ...LEDGER_VALUES, code: '61.03.002' }, PLACED)).toEqual({ code: '61.03.002' })
  })
})
