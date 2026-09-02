import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/work-orders/work-order-form-payload'
import type { WorkOrderDetail } from '@/features/work-orders/types'
import type { WorkOrderFormValues } from '@/features/work-orders/use-work-order-form'

const formValues: WorkOrderFormValues = {
  code: 'COM-0001',
  quote_id: 4,
  title: 'Installazione impianto',
  type: 'processing',
  callback_date: null,
  description: null,
  internal_notes: null,
  is_force_closed: false,
  force_close_reason: null,
  quote_line_ids: [11, 12],
}

function original(overrides: Partial<WorkOrderDetail> = {}): WorkOrderDetail {
  return {
    id: 7,
    code: 'COM-0001',
    title: 'Installazione impianto',
    type: 'processing',
    status: { value: 'open', is_force_closed: false },
    is_force_closed: false,
    force_close_reason: null,
    callback_date: null,
    description: null,
    internal_notes: null,
    contract_number: 'QUO-0004',
    quote: { id: 4, code: 'QUO-0004', title: 'Fornitura annuale' },
    quote_lines: [
      { id: 11, sort_order: 1, product: { id: 1, code: 'PRD-0001', name: 'Consulenza' } },
      { id: 12, sort_order: 2, product: { id: 2, code: 'PRD-0002', name: 'Installazione' } },
    ],
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('buildCreatePayload (spec 0093, D-1)', () => {
  it('includes code when set', () => {
    expect(buildCreatePayload(formValues)).toEqual({
      code: 'COM-0001',
      quote_id: 4,
      title: 'Installazione impianto',
      type: 'processing',
      callback_date: null,
      description: null,
      internal_notes: null,
      is_force_closed: false,
      force_close_reason: null,
      quote_line_ids: [11, 12],
    })
  })

  it('omits an empty code so the server generates the sequential one', () => {
    const payload = buildCreatePayload({ ...formValues, code: '  ' })
    expect(payload).not.toHaveProperty('code')
  })

  it('never sends a reason unless force-closed (D-4)', () => {
    const payload = buildCreatePayload({
      ...formValues,
      is_force_closed: false,
      force_close_reason: 'Leftover text',
    })
    expect(payload.force_close_reason).toBeNull()
  })
})

describe('buildUpdatePayload (spec 0093, AC-077)', () => {
  it('omits every field when nothing changed (diff-only)', () => {
    expect(buildUpdatePayload(formValues, original())).toEqual({})
  })

  it('includes only the changed title', () => {
    expect(buildUpdatePayload({ ...formValues, title: 'Nuovo titolo' }, original())).toEqual({
      title: 'Nuovo titolo',
    })
  })

  it('includes only the changed type', () => {
    expect(buildUpdatePayload({ ...formValues, type: 'project' }, original())).toEqual({
      type: 'project',
    })
  })

  it('NEVER includes code, even when the form value diverges from the original (D-1/AC-074)', () => {
    const payload = buildUpdatePayload({ ...formValues, code: 'COM-9999' }, original())
    expect(payload).not.toHaveProperty('code')
  })

  it('NEVER includes quote_id, even when the form value diverges from the original (D-5/AC-074)', () => {
    const payload = buildUpdatePayload({ ...formValues, quote_id: 99 }, original())
    expect(payload).not.toHaveProperty('quote_id')
  })

  it('sends quote_line_ids only when the selected set actually differs', () => {
    expect(buildUpdatePayload(formValues, original())).not.toHaveProperty('quote_line_ids')

    expect(
      buildUpdatePayload({ ...formValues, quote_line_ids: [12, 13] }, original()),
    ).toEqual({ quote_line_ids: [12, 13] })
  })

  it('a reorder of the same set is not a change (order-insensitive)', () => {
    expect(
      buildUpdatePayload({ ...formValues, quote_line_ids: [12, 11] }, original()),
    ).not.toHaveProperty('quote_line_ids')
  })

  it('clears force_close_reason to null the moment is_force_closed turns off (D-4)', () => {
    const closedOriginal = original({ is_force_closed: true, force_close_reason: 'Motivo precedente' })

    const payload = buildUpdatePayload(
      { ...formValues, is_force_closed: false, force_close_reason: null },
      closedOriginal,
    )

    expect(payload).toEqual({ is_force_closed: false, force_close_reason: null })
  })
})
