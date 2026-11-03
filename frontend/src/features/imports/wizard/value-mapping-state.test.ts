import { describe, expect, it } from 'vitest'
import type { ValueMappingField } from '@/features/imports/wizard/types'
import { isCompleteEntry, resolveValueMappings } from '@/features/imports/wizard/value-mapping-state'

const FIELD: ValueMappingField = {
  field: 'workflow_status',
  column_key: 'Stato',
  allow_create: true,
  options: [
    { id: 1, name: 'Open', group: 'open' },
    { id: 2, name: 'Pending', group: 'pending' },
  ],
  values: [
    { value: 'A', count: 2, suggested_id: 1 },
    { value: 'B', count: 1, suggested_id: null },
  ],
}

describe('value mapping state', () => {
  it('layers manual choice over template over suggestion and counts what is still unmatched', () => {
    const none = resolveValueMappings([FIELD], {}, null)
    expect(none.unmatched).toBe(1)
    expect(none.payload).toEqual({ workflow_status: { A: { id: 1 } } })

    const merged = resolveValueMappings(
      [FIELD],
      { workflow_status: { A: { id: 2 } } },
      { workflow_status: { b: { id: 1 }, a: { id: 1 } } },
    )
    expect(merged.unmatched).toBe(0)
    expect(merged.payload.workflow_status).toEqual({ A: { id: 2 }, B: { id: 1 } })
  })

  it('treats a create entry with a blank name as unmatched', () => {
    expect(isCompleteEntry({ create: { name: '  ', group: 'open' } })).toBe(false)
    expect(isCompleteEntry({ create: { name: 'New', group: 'open' } })).toBe(true)
    expect(resolveValueMappings([FIELD], { workflow_status: { B: { create: { name: '', group: 'open' } } } }, null).unmatched).toBe(1)
  })
})
