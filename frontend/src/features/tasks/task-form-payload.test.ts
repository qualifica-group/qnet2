import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/tasks/task-form-payload'
import {
  taskDetail as task,
  taskFormValues as values,
  taskRecurrenceDetail,
  taskRecurrenceFormValues as recurrence,
} from '@/features/tasks/task-fixtures'

describe('buildCreatePayload', () => {
  it('sends the whole frozen contract, arrays included', () => {
    const payload = buildCreatePayload(values())

    expect(payload.title).toBe('Richiamare il cliente')
    expect(payload.requester_id).toBe(21)
    expect(payload.end_date).toBe('2026-09-05')
    expect(payload.assignee_ids).toEqual([31, 32])
    expect(payload.watcher_ids).toEqual([41])
    expect(payload.start_time).toBe('09:00')
    expect(payload.estimated_minutes).toBe(90)
  })

  /** Spec 0146 D-2: sent on create like any other scalar, `null` when unpicked. */
  it('sends work_order_stage_id', () => {
    expect(buildCreatePayload(values()).work_order_stage_id).toBeNull()
    expect(buildCreatePayload(values({ work_order_stage_id: 7 })).work_order_stage_id).toBe(7)
  })

  it('AC-011: never carries creator_id nor completion_percentage', () => {
    const payload = buildCreatePayload(values())

    expect(payload).not.toHaveProperty('creator_id')
    expect(payload).not.toHaveProperty('completion_percentage')
  })

  it('AC-007 (spec 0127): never carries completion_date — only the completion actions write it', () => {
    expect(buildCreatePayload(values())).not.toHaveProperty('completion_date')
  })

  it('AC-009: never carries task_status_id — the server derives it on create (D-3)', () => {
    const payload = buildCreatePayload(values())

    expect(payload).not.toHaveProperty('task_status_id')
  })

  it('AC-017 (spec 0121): sends requires_validation alongside requires_closure_feedback', () => {
    const payload = buildCreatePayload(values({ requires_validation: true }))

    expect(payload.requires_validation).toBe(true)
    expect(payload).not.toHaveProperty('closure_feedback')
  })

  /** Spec 0128 AC-023: an empty `RichTextEditor` emits `null`, sent as-is. */
  it('sends description null when the editor is empty', () => {
    expect(buildCreatePayload(values({ description: null })).description).toBeNull()
  })
})

/** Spec 0120 D-1/D-12/AC-032: only the pertinent fields for the picked frequency/ends travel. */
describe('buildCreatePayload — recurrence', () => {
  it('sends null when the section is disabled', () => {
    expect(buildCreatePayload(values()).recurrence).toBeNull()
  })

  it('sends only frequency/interval/ends/weekdays for a weekly rule', () => {
    const payload = buildCreatePayload(
      values({ recurrence: recurrence({ enabled: true, frequency: 'weekly', ends: 'never', weekdays: [1, 3] }) }),
    )

    expect(payload.recurrence).toEqual({ frequency: 'weekly', interval: 1, ends: 'never', weekdays: [1, 3] })
  })

  it('sends month_day and ends_on for a monthly rule ending on a date', () => {
    const payload = buildCreatePayload(
      values({
        recurrence: recurrence({
          enabled: true,
          frequency: 'monthly',
          ends: 'on_date',
          month_day: 31,
          ends_on: '2027-03-31',
        }),
      }),
    )

    expect(payload.recurrence).toEqual({
      frequency: 'monthly',
      interval: 1,
      ends: 'on_date',
      month_day: 31,
      ends_on: '2027-03-31',
    })
  })

  it('sends occurrence_count for a rule ending after a count', () => {
    const payload = buildCreatePayload(
      values({
        recurrence: recurrence({ enabled: true, frequency: 'daily', ends: 'after_count', occurrence_count: 5 }),
      }),
    )

    expect(payload.recurrence).toEqual({ frequency: 'daily', interval: 1, ends: 'after_count', occurrence_count: 5 })
  })

  it('drops the key entirely when the actor may not edit the field (D-12)', () => {
    const payload = buildCreatePayload(
      values({ recurrence: recurrence({ enabled: true, frequency: 'daily', ends: 'never' }) }),
      false,
    )

    expect(payload).not.toHaveProperty('recurrence')
  })
})

describe('buildUpdatePayload', () => {
  it('sends nothing when nothing changed', () => {
    expect(buildUpdatePayload(values(), task())).toEqual({})
  })

  /** Spec 0128 AC-023: the description is a `RichTextHtml`, same "changed only" wire contract as any scalar. */
  it('sends the description only when its HTML actually changed, null included', () => {
    expect(buildUpdatePayload(values(), task())).not.toHaveProperty('description')

    const changed = buildUpdatePayload(values({ description: '<p><strong>Ciao</strong></p>' }), task())
    expect(changed.description).toBe('<p><strong>Ciao</strong></p>')

    const cleared = buildUpdatePayload(values({ description: null }), task())
    expect(cleared).toHaveProperty('description', null)
  })

  it('AC-007 (spec 0127): never carries completion_date, even when the task already has one', () => {
    const payload = buildUpdatePayload(values({ title: 'Nuovo titolo' }), task({ completion_date: '2026-09-10' }))

    expect(payload).toEqual({ title: 'Nuovo titolo' })
  })

  it('AC-012: a title-only edit does not resend the two user arrays', () => {
    const payload = buildUpdatePayload(values({ title: 'Nuovo titolo' }), task())

    expect(payload).toEqual({ title: 'Nuovo titolo' })
    expect(payload).not.toHaveProperty('assignee_ids')
    expect(payload).not.toHaveProperty('watcher_ids')
  })

  it('AC-012: an emptied assignee selection still travels as []', () => {
    const payload = buildUpdatePayload(values({ assignee_ids: [] }), task())

    expect(payload.assignee_ids).toEqual([])
    expect(payload).not.toHaveProperty('watcher_ids')
  })

  it('syncs both pivots independently when both change (mechanics only — D-9 overlap is a schema-level rule, see task-schema.test.ts)', () => {
    const payload = buildUpdatePayload(values({ assignee_ids: [31, 41], watcher_ids: [41, 31] }), task())

    expect(payload.assignee_ids).toEqual([31, 41])
    expect(payload.watcher_ids).toEqual([41, 31])
  })

  it('reorders alone are a no-op: the two pivots are unordered sets', () => {
    const payload = buildUpdatePayload(values({ assignee_ids: [32, 31] }), task())

    expect(payload).not.toHaveProperty('assignee_ids')
  })

  it('sends the status only when it actually changed', () => {
    expect(buildUpdatePayload(values({ task_status_id: 3 }), task())).not.toHaveProperty('task_status_id')
    expect(buildUpdatePayload(values({ task_status_id: 5 }), task()).task_status_id).toBe(5)
  })

  it('clears a relation by sending an explicit null', () => {
    const payload = buildUpdatePayload(values({ referent_id: null }), task())

    expect(payload).toHaveProperty('referent_id', null)
  })

  /** Spec 0146 D-2/AC-015/AC-016: same "changed only" wire contract as any scalar. */
  it('sends work_order_stage_id only when it actually changed', () => {
    expect(buildUpdatePayload(values({ work_order_stage_id: null }), task())).not.toHaveProperty(
      'work_order_stage_id',
    )

    const payload = buildUpdatePayload(values({ work_order_stage_id: 7 }), task())
    expect(payload.work_order_stage_id).toBe(7)
  })

  it('clears the fase by sending an explicit null when it was previously set', () => {
    const payload = buildUpdatePayload(values({ work_order_stage_id: null }), task({ work_order_stage_id: 7 }))

    expect(payload).toHaveProperty('work_order_stage_id', null)
  })

  it('AC-011/AC-084: never carries creator_id nor completion_percentage, whatever changed', () => {
    const payload = buildUpdatePayload(values({ title: 'X', task_status_id: 99 }), task())

    expect(payload).not.toHaveProperty('creator_id')
    expect(payload).not.toHaveProperty('completion_percentage')
  })

  it('AC-045 (spec 0116 D-6): is_blocked is no longer a key `TaskFormValues` can carry', () => {
    const payload = buildUpdatePayload(values({ title: 'X' }), task())

    expect(payload).not.toHaveProperty('is_blocked')
  })

  it('AC-017 (spec 0121): sends requires_validation only when it actually changed', () => {
    expect(buildUpdatePayload(values({ requires_validation: false }), task())).not.toHaveProperty(
      'requires_validation',
    )
    expect(buildUpdatePayload(values({ requires_validation: true }), task()).requires_validation).toBe(
      true,
    )
  })
})

/**
 * Spec 0120 D-10/D-12: "chiave assente = invariata, null = cancella, oggetto
 * = crea/sostituisce" — the diff must reproduce all three, and never touch
 * the key at all when the actor lacks the mandate.
 */
describe('buildUpdatePayload — recurrence', () => {
  it('sends nothing when neither side has a recurrence', () => {
    expect(buildUpdatePayload(values(), task({ recurrence: null }))).not.toHaveProperty('recurrence')
  })

  it('sends nothing when the persisted rule is unchanged, weekday order included', () => {
    const persisted = taskRecurrenceDetail({
      frequency: 'weekly',
      interval: 1,
      weekdays: [1, 3],
      ends: 'never',
      ends_on: null,
    })
    const payload = buildUpdatePayload(
      values({
        recurrence: recurrence({ enabled: true, frequency: 'weekly', ends: 'never', weekdays: [3, 1] }),
      }),
      task({ recurrence: persisted }),
    )

    expect(payload).not.toHaveProperty('recurrence')
  })

  it('sends the object when a recurrence is newly enabled', () => {
    const payload = buildUpdatePayload(
      values({ recurrence: recurrence({ enabled: true, frequency: 'daily', ends: 'never' }) }),
      task({ recurrence: null }),
    )

    expect(payload.recurrence).toEqual({ frequency: 'daily', interval: 1, ends: 'never' })
  })

  it('sends null when a persisted recurrence is disabled (D-10: the series is cancelled)', () => {
    const payload = buildUpdatePayload(values(), task({ recurrence: taskRecurrenceDetail() }))

    expect(payload).toHaveProperty('recurrence', null)
  })

  it('sends the new object when the persisted rule actually changed', () => {
    const persisted = taskRecurrenceDetail({ frequency: 'daily', interval: 1, ends: 'never', ends_on: null })
    const payload = buildUpdatePayload(
      values({ recurrence: recurrence({ enabled: true, frequency: 'daily', ends: 'never', interval: 3 }) }),
      task({ recurrence: persisted }),
    )

    expect(payload.recurrence).toEqual({ frequency: 'daily', interval: 3, ends: 'never' })
  })

  it('drops the key entirely when the actor may not edit the field, whatever changed (D-12)', () => {
    const payload = buildUpdatePayload(values(), task({ recurrence: taskRecurrenceDetail() }), false)

    expect(payload).not.toHaveProperty('recurrence')
  })
})
