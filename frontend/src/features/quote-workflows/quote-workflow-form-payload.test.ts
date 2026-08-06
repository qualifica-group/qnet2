import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildDefaultStatusesPayload,
  buildUpdatePayload,
} from '@/features/quote-workflows/quote-workflow-form-payload'
import type { CreateQuoteWorkflowFormValues } from '@/features/quote-workflows/quote-workflow-schema'
import type { WorkflowStatusFormRow } from '@/features/quote-workflows/types'

const values: CreateQuoteWorkflowFormValues = {
  name: 'EMEA workflow',
  is_active: true,
  criteria: [
    { field: 'state_id', value_id: 1 },
    { field: 'source_id', value_id: 2 },
  ],
}

const CREATE_STATUS_ROWS: WorkflowStatusFormRow[] = [
  { id: 'system-open', name: 'Aperto', color: null, group: 'open', system_key: 'open', description: null, requires_note: false },
  { id: 'c1', name: 'In progress', color: 'blue', group: 'pending', system_key: null, description: null, requires_note: false },
  { id: 'system-closed-won', name: 'Chiuso positivo', color: null, group: 'closed_won', system_key: 'closed_won', description: null, requires_note: false },
  { id: 'system-closed-lost', name: 'Chiuso negativo', color: null, group: 'closed_lost', system_key: 'closed_lost', description: null, requires_note: false },
]

const PERSISTED_STATUS_ROWS: WorkflowStatusFormRow[] = [
  { id: '1', statusId: 1, name: 'Open', color: null, group: 'open', system_key: 'open', description: null, requires_note: false },
  { id: '2', statusId: 2, name: 'In progress', color: 'blue', group: 'pending', system_key: null, description: null, requires_note: false },
  { id: '3', name: 'New custom', color: 'green', group: 'pending', system_key: null, description: null, requires_note: false },
  { id: '4', statusId: 4, name: 'Closed won', color: null, group: 'closed_won', system_key: 'closed_won', description: null, requires_note: false },
  { id: '5', statusId: 5, name: 'Closed lost', color: null, group: 'closed_lost', system_key: 'closed_lost', description: null, requires_note: false },
]

describe('buildCreatePayload', () => {
  it('builds the full create payload: pinned rows (tagged system_key) + custom rows, in order', () => {
    expect(buildCreatePayload(values, CREATE_STATUS_ROWS)).toEqual({
      name: 'EMEA workflow',
      is_active: true,
      criteria: [
        { field: 'state_id', value_id: 1 },
        { field: 'source_id', value_id: 2 },
      ],
      statuses: [
        { name: 'Aperto', color: null, group: 'open', system_key: 'open', description: null, requires_note: false },
        { name: 'In progress', color: 'blue', group: 'pending', system_key: null, description: null, requires_note: false },
        { name: 'Chiuso positivo', color: null, group: 'closed_won', system_key: 'closed_won', description: null, requires_note: false },
        { name: 'Chiuso negativo', color: null, group: 'closed_lost', system_key: 'closed_lost', description: null, requires_note: false },
      ],
    })
  })

  it('drops an incomplete criteria row (field or value still unset)', () => {
    const incomplete: CreateQuoteWorkflowFormValues = {
      ...values,
      criteria: [{ field: 'state_id', value_id: 1 }, { field: null, value_id: null }],
    }
    expect(buildCreatePayload(incomplete, []).criteria).toEqual([{ field: 'state_id', value_id: 1 }])
  })

  it('sends the pinned rows with their system_key and no id (nothing persisted yet)', () => {
    const payload = buildCreatePayload(values, CREATE_STATUS_ROWS)
    const systemKeys = payload.statuses?.map((status) => status.system_key)
    expect(systemKeys).toEqual(['open', null, 'closed_won', 'closed_lost'])
    expect(payload.statuses?.every((status) => !('id' in status))).toBe(true)
  })
})

describe('buildUpdatePayload', () => {
  it('sends the full authoritative criteria + statuses sync, including ids for persisted rows', () => {
    expect(buildUpdatePayload(values, PERSISTED_STATUS_ROWS)).toEqual({
      name: 'EMEA workflow',
      is_active: true,
      criteria: [
        { field: 'state_id', value_id: 1 },
        { field: 'source_id', value_id: 2 },
      ],
      // `system_key` travels on every row, `null` included: only an
      // explicitly-sent key may release the optional `validated` mark.
      statuses: [
        { id: 1, name: 'Open', description: null, color: null, group: 'open', requires_note: false, system_key: 'open' },
        { id: 2, name: 'In progress', description: null, color: 'blue', group: 'pending', requires_note: false, system_key: null },
        { id: undefined, name: 'New custom', description: null, color: 'green', group: 'pending', requires_note: false, system_key: null },
        { id: 4, name: 'Closed won', description: null, color: null, group: 'closed_won', requires_note: false, system_key: 'closed_won' },
        { id: 5, name: 'Closed lost', description: null, color: null, group: 'closed_lost', requires_note: false, system_key: 'closed_lost' },
      ],
    })
  })
})

describe('buildDefaultStatusesPayload', () => {
  it('wraps the same statuses sync shape under `statuses`', () => {
    expect(buildDefaultStatusesPayload(PERSISTED_STATUS_ROWS).statuses).toHaveLength(5)
  })
})
