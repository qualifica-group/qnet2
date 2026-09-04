import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/tasks/task-form-payload'
import { taskDetail as task, taskFormValues as values } from '@/features/tasks/task-fixtures'

describe('buildCreatePayload', () => {
  it('sends the whole frozen contract, arrays included', () => {
    const payload = buildCreatePayload(values())

    expect(payload.title).toBe('Richiamare il cliente')
    expect(payload.task_status_id).toBe(3)
    expect(payload.assignee_ids).toEqual([31, 32])
    expect(payload.watcher_ids).toEqual([41])
    expect(payload.start_time).toBe('09:00')
    expect(payload.estimated_minutes).toBe(90)
  })

  it('AC-011: never carries creator_id nor completion_percentage', () => {
    const payload = buildCreatePayload(values())

    expect(payload).not.toHaveProperty('creator_id')
    expect(payload).not.toHaveProperty('completion_percentage')
  })
})

describe('buildUpdatePayload', () => {
  it('sends nothing when nothing changed', () => {
    expect(buildUpdatePayload(values(), task())).toEqual({})
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

  it('AC-083: the same user in both sets is sent to both pivots', () => {
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

  it('AC-011/AC-084: never carries creator_id nor completion_percentage, whatever changed', () => {
    const payload = buildUpdatePayload(
      values({ title: 'X', task_status_id: 99, is_blocked: true }),
      task(),
    )

    expect(payload).not.toHaveProperty('creator_id')
    expect(payload).not.toHaveProperty('completion_percentage')
  })

  it('AC-086: the blocked flag travels on its own, without touching the status', () => {
    const payload = buildUpdatePayload(values({ is_blocked: true }), task())

    expect(payload).toEqual({ is_blocked: true })
  })
})
