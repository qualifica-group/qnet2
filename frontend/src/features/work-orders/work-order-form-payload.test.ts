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
  start_date: '2026-03-01',
  supervisor_ids: [21],
  participant_slots: [31, null],
  callback_date: null,
  description: null,
  internal_notes: null,
  is_force_closed: false,
  force_close_reason: null,
  quote_line_ids: [11, 12],
  attribute_values: {},
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
    start_date: '2026-03-01',
    supervisors: [{ id: 21, name: 'Ada Alberti' }],
    participants: [{ id: 31, name: 'Bruno Bianchi', position: 1 }],
    callback_date: null,
    description: null,
    internal_notes: null,
    contract_number: 'QUO-0004',
    quote: { id: 4, code: 'QUO-0004', title: 'Fornitura annuale' },
    quote_lines: [
      { id: 11, sort_order: 1, product: { id: 1, code: 'PRD-0001', name: 'Consulenza' } },
      { id: 12, sort_order: 2, product: { id: 2, code: 'PRD-0002', name: 'Installazione' } },
    ],
    applicable_attributes: [],
    attribute_layout: null,
    attribute_values: {},
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
      start_date: '2026-03-01',
      supervisor_ids: [21],
      participant_slots: [31, null],
      callback_date: null,
      description: null,
      internal_notes: null,
      is_force_closed: false,
      force_close_reason: null,
      quote_line_ids: [11, 12],
      attribute_values: {},
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

describe('buildUpdatePayload — responsabili and partecipanti (spec 0096, AC-073)', () => {
  it('sends neither relation when both are unchanged', () => {
    expect(buildUpdatePayload(formValues, original())).toEqual({})
  })

  it('sends supervisor_ids only when the responsabili actually change', () => {
    expect(buildUpdatePayload({ ...formValues, supervisor_ids: [21, 22] }, original())).toEqual({
      supervisor_ids: [21, 22],
    })
  })

  it('treats a mere reorder of the responsabili as no change (it is a set)', () => {
    const withTwo = original({ supervisors: [{ id: 21, name: 'Ada' }, { id: 22, name: 'Bruno' }] })

    expect(buildUpdatePayload({ ...formValues, supervisor_ids: [22, 21] }, withTwo)).toEqual({})
  })

  it('sends participant_slots when a partecipante MOVES slot: position is meaningful', () => {
    expect(buildUpdatePayload({ ...formValues, participant_slots: [null, 31] }, original())).toEqual({
      participant_slots: [null, 31],
    })
  })

  it('sends participant_slots when a partecipante is added', () => {
    expect(buildUpdatePayload({ ...formValues, participant_slots: [31, 32] }, original())).toEqual({
      participant_slots: [31, 32],
    })
  })

  it('sends start_date only when it changes', () => {
    expect(buildUpdatePayload({ ...formValues, start_date: '2026-04-02' }, original())).toEqual({
      start_date: '2026-04-02',
    })
  })
})

describe('buildCreatePayload / buildUpdatePayload — attribute_values (spec 0098)', () => {
  it('create always sends the map, even when empty', () => {
    expect(buildCreatePayload(formValues).attribute_values).toEqual({})
  })

  it('update omits the map when nothing changed from the seeded original', () => {
    const withAttribute = original({
      applicable_attributes: [
        {
          id: 1,
          code: 'site_access',
          name: 'Site access',
          type: 'text',
          description: null,
          help_text: null,
          placeholder: null,
          icon: null,
          config: null,
          relation_target: null,
          is_required: false,
          sort_order: 0,
          options: [],
        },
      ],
      attribute_values: { site_access: 'Gate 3' },
    })

    expect(
      buildUpdatePayload({ ...formValues, attribute_values: { site_access: 'Gate 3' } }, withAttribute),
    ).toEqual({})
  })

  it('update sends the whole map when a code actually changed', () => {
    const withAttribute = original({
      applicable_attributes: [
        {
          id: 1,
          code: 'site_access',
          name: 'Site access',
          type: 'text',
          description: null,
          help_text: null,
          placeholder: null,
          icon: null,
          config: null,
          relation_target: null,
          is_required: false,
          sort_order: 0,
          options: [],
        },
      ],
      attribute_values: { site_access: 'Gate 3' },
    })

    expect(
      buildUpdatePayload({ ...formValues, attribute_values: { site_access: 'Gate 4' } }, withAttribute),
    ).toEqual({ attribute_values: { site_access: 'Gate 4' } })
  })
})
