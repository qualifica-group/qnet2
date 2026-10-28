import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildDefaultStatusesPayload,
  buildUpdatePayload,
} from '@/features/work-order-workflows/work-order-workflow-form-payload'
import type { WorkOrderWorkflowFormValues } from '@/features/work-order-workflows/work-order-workflow-schema'
import type { WorkOrderStatusFormRow, WorkOrderWorkflowDetail } from '@/features/work-order-workflows/types'

const values: WorkOrderWorkflowFormValues = {
  name: 'SOA set',
  is_active: true,
  criteria: [
    { field: 'business_function_id', value_id: 1 },
    { field: 'product_category_id', value_id: 2 },
  ],
  statuses: [],
}

function row(overrides: Partial<WorkOrderStatusFormRow> & Pick<WorkOrderStatusFormRow, 'id' | 'name'>): WorkOrderStatusFormRow {
  return {
    description: null,
    color: null,
    group: 'in_progress',
    system_key: null,
    progress_percentage: null,
    ...overrides,
  }
}

const CREATE_ROWS: WorkOrderStatusFormRow[] = [
  row({ id: 'system-open', name: 'Aperta', group: 'open', system_key: 'open', progress_percentage: 0 }),
  row({ id: 'c1', name: 'Review', color: 'blue', progress_percentage: 40 }),
  row({ id: 'system-completed', name: 'Completata', group: 'completed', system_key: 'completed', progress_percentage: 100 }),
  row({ id: 'system-closed', name: 'Chiusa', group: 'closed', system_key: 'closed' }),
]

const SAVED_ROWS: WorkOrderStatusFormRow[] = [
  row({ id: '1', statusId: 1, name: 'Open', group: 'open', system_key: 'open' }),
  row({ id: '2', statusId: 2, name: 'Review', color: 'blue', progress_percentage: 40 }),
  row({ id: '4', statusId: 4, name: 'Completed', group: 'completed', system_key: 'completed', progress_percentage: 100 }),
]

const ORIGINAL: WorkOrderWorkflowDetail = {
  id: 9,
  name: 'SOA set',
  is_active: true,
  criteria: [
    { id: 1, field: 'business_function_id', value_id: 1, value_label: 'A' },
    { id: 2, field: 'product_category_id', value_id: 2, value_label: 'B' },
  ],
  statuses: [
    { id: 1, name: 'Open', color: null, sort_order: 0, system_key: 'open', group: 'open', description: null, progress_percentage: null },
    { id: 2, name: 'Review', color: 'blue', sort_order: 10, system_key: null, group: 'in_progress', description: null, progress_percentage: 40 },
    { id: 4, name: 'Completed', color: null, sort_order: 20, system_key: 'completed', group: 'completed', description: null, progress_percentage: 100 },
  ],
  created_at: '2026-01-01T00:00:00Z',
  updated_at: '2026-01-01T00:00:00Z',
}

describe('buildCreatePayload', () => {
  it('sends pinned rows (tagged system_key) and custom rows in order, with their progress_percentage', () => {
    const payload = buildCreatePayload({ ...values, statuses: CREATE_ROWS })
    expect(payload.name).toBe('SOA set')
    expect(payload.criteria).toHaveLength(2)
    expect(payload.statuses?.map((status) => [status.system_key, status.progress_percentage])).toEqual([
      ['open', 0],
      [null, 40],
      ['completed', 100],
      ['closed', null],
    ])
    expect(payload.statuses?.every((status) => !('id' in status))).toBe(true)
  })

  it('drops an incomplete criteria row', () => {
    const incomplete = { ...values, criteria: [{ field: 'business_function_id', value_id: 1 }, { field: null, value_id: null }] }
    expect(buildCreatePayload(incomplete).criteria).toEqual([{ field: 'business_function_id', value_id: 1 }])
  })
})

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

  it('sends the statuses sync, with ids and no system_key, when a percentage changed', () => {
    const rows = [SAVED_ROWS[0], { ...SAVED_ROWS[1], progress_percentage: 60 }, row({ id: '3', name: 'New' }), SAVED_ROWS[2]]
    const payload = buildUpdatePayload({ ...values, statuses: rows }, ORIGINAL)
    expect(Object.keys(payload)).toEqual(['statuses'])
    expect(payload.statuses?.map((status) => [status.id, status.progress_percentage])).toEqual([
      [1, null],
      [2, 60],
      [undefined, null],
      [4, 100],
    ])
  })

  it('sends the statuses when only their order changed', () => {
    const reordered = [SAVED_ROWS[1], SAVED_ROWS[0], SAVED_ROWS[2]]
    expect(Object.keys(buildUpdatePayload({ ...values, statuses: reordered }, ORIGINAL))).toEqual(['statuses'])
  })
})

describe('buildDefaultStatusesPayload', () => {
  it('wraps the statuses sync shape under `statuses`', () => {
    expect(buildDefaultStatusesPayload(SAVED_ROWS).statuses).toHaveLength(3)
  })
})
