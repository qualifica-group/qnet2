import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildDefaultStatusesPayload,
  buildUpdatePayload,
} from '@/features/quote-workflows/quote-workflow-form-payload'
import type { CreateQuoteWorkflowFormValues } from '@/features/quote-workflows/quote-workflow-schema'
import type { QuoteWorkflowDetail, WorkflowStatusFormRow } from '@/features/quote-workflows/types'

const values: CreateQuoteWorkflowFormValues = {
  name: 'EMEA workflow',
  is_active: true,
  criteria: [
    { field: 'business_function_id', value_id: 1 },
    { field: 'source_id', value_id: 2 },
  ],
  statuses: [],
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
    expect(buildCreatePayload({ ...values, statuses: CREATE_STATUS_ROWS })).toEqual({
      name: 'EMEA workflow',
      is_active: true,
      criteria: [
        { field: 'business_function_id', value_id: 1 },
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
      criteria: [{ field: 'business_function_id', value_id: 1 }, { field: null, value_id: null }],
    }
    expect(buildCreatePayload(incomplete).criteria).toEqual([{ field: 'business_function_id', value_id: 1 }])
  })

  it('sends the pinned rows with their system_key and no id (nothing persisted yet)', () => {
    const payload = buildCreatePayload({ ...values, statuses: CREATE_STATUS_ROWS })
    const systemKeys = payload.statuses?.map((status) => status.system_key)
    expect(systemKeys).toEqual(['open', null, 'closed_won', 'closed_lost'])
    expect(payload.statuses?.every((status) => !('id' in status))).toBe(true)
  })
})

const ORIGINAL: QuoteWorkflowDetail = {
  id: 9,
  name: 'EMEA workflow',
  is_active: true,
  criteria: [
    { id: 1, field: 'business_function_id', value_id: 1, value_label: 'A', field_label: 'x', field_source: 'native' },
    { id: 2, field: 'source_id', value_id: 2, value_label: 'B', field_label: 'y', field_source: 'native' },
  ],
  statuses: [
    { id: 1, name: 'Open', color: null, sort_order: 0, system_key: 'open', group: 'open', description: null, requires_note: false },
    { id: 2, name: 'In progress', color: 'blue', sort_order: 10, system_key: null, group: 'pending', description: null, requires_note: false },
    { id: 4, name: 'Closed won', color: null, sort_order: 20, system_key: 'closed_won', group: 'closed_won', description: null, requires_note: false },
  ],
  created_at: '2026-01-01T00:00:00Z',
  updated_at: '2026-01-01T00:00:00Z',
}

const SAVED_ROWS: WorkflowStatusFormRow[] = [
  { id: '1', statusId: 1, name: 'Open', color: null, group: 'open', system_key: 'open', description: null, requires_note: false },
  { id: '2', statusId: 2, name: 'In progress', color: 'blue', group: 'pending', system_key: null, description: null, requires_note: false },
  { id: '4', statusId: 4, name: 'Closed won', color: null, group: 'closed_won', system_key: 'closed_won', description: null, requires_note: false },
]

describe('buildUpdatePayload', () => {
  it('is empty when nothing changed', () => {
    expect(buildUpdatePayload({ ...values, statuses: SAVED_ROWS }, ORIGINAL)).toEqual({})
  })

  it('sends only the changed scalar fields', () => {
    expect(buildUpdatePayload({ ...values, name: 'Renamed', is_active: false, statuses: SAVED_ROWS }, ORIGINAL)).toEqual({
      name: 'Renamed',
      is_active: false,
    })
  })

  it('sends the whole criteria array, and nothing else, when a criterion changed', () => {
    const changed = { ...values, criteria: [{ field: 'source_id', value_id: 2 }], statuses: SAVED_ROWS }
    expect(buildUpdatePayload(changed, ORIGINAL)).toEqual({ criteria: [{ field: 'source_id', value_id: 2 }] })
  })

  it('sends the whole statuses sync, and nothing else, when a status changed: persisted rows keep their id', () => {
    const rows = [SAVED_ROWS[0], SAVED_ROWS[1], PERSISTED_STATUS_ROWS[2], SAVED_ROWS[2]]
    // No `system_key` on update: a system row is identified by its persisted `id`.
    expect(buildUpdatePayload({ ...values, statuses: rows }, ORIGINAL)).toEqual({
      statuses: [
        { id: 1, name: 'Open', description: null, color: null, group: 'open', requires_note: false },
        { id: 2, name: 'In progress', description: null, color: 'blue', group: 'pending', requires_note: false },
        { id: undefined, name: 'New custom', description: null, color: 'green', group: 'pending', requires_note: false },
        { id: 4, name: 'Closed won', description: null, color: null, group: 'closed_won', requires_note: false },
      ],
    })
  })

  it('sends the statuses when only their order changed', () => {
    const reordered = [SAVED_ROWS[1], SAVED_ROWS[0], SAVED_ROWS[2]]
    expect(Object.keys(buildUpdatePayload({ ...values, statuses: reordered }, ORIGINAL))).toEqual(['statuses'])
  })
})

describe('buildDefaultStatusesPayload', () => {
  it('wraps the same statuses sync shape under `statuses`', () => {
    expect(buildDefaultStatusesPayload(PERSISTED_STATUS_ROWS).statuses).toHaveLength(5)
  })
})
