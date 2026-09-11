import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildTaskSchema, isClosingStatus } from '@/features/tasks/task-schema'
import { taskFormValues as values } from '@/features/tasks/task-fixtures'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

/** The `path` of every issue the schema raised, so assertions never depend on a message. */
function issuePaths(result: ReturnType<ReturnType<typeof buildTaskSchema>['safeParse']>): string[] {
  return result.success ? [] : result.error.issues.map((issue) => issue.path.join('.'))
}

describe('buildTaskSchema — required fields', () => {
  it('accepts a task carrying only a title and a status', () => {
    expect(buildTaskSchema(i18n.t).safeParse(values()).success).toBe(true)
  })

  it('rejects an empty title', () => {
    expect(issuePaths(buildTaskSchema(i18n.t).safeParse(values({ title: '' })))).toContain('title')
  })

  it('rejects an unpicked status in edit mode (`task_status_id` is NOT NULL server-side)', () => {
    expect(issuePaths(buildTaskSchema(i18n.t).safeParse(values({ task_status_id: null })))).toContain(
      'task_status_id',
    )
  })

  it('rejects a missing requester (spec 0118 D-1/D-2, both modes)', () => {
    expect(issuePaths(buildTaskSchema(i18n.t).safeParse(values({ requester_id: null })))).toContain(
      'requester_id',
    )
  })

  it('rejects an empty assignee list (spec 0118 D-1/D-2, both modes)', () => {
    expect(issuePaths(buildTaskSchema(i18n.t).safeParse(values({ assignee_ids: [] })))).toContain(
      'assignee_ids',
    )
  })

  it('rejects a missing end date (spec 0118 D-1/D-2, both modes)', () => {
    expect(issuePaths(buildTaskSchema(i18n.t).safeParse(values({ end_date: null })))).toContain('end_date')
  })

  it('rejects a negative or fractional estimate (unsignedInteger minutes, D-11)', () => {
    expect(issuePaths(buildTaskSchema(i18n.t).safeParse(values({ estimated_minutes: -5 })))).toContain(
      'estimated_minutes',
    )
    expect(issuePaths(buildTaskSchema(i18n.t).safeParse(values({ estimated_minutes: 12.5 })))).toContain(
      'estimated_minutes',
    )
  })
})

/** D-7 replicated client-side for UX; the server stays the authority (AC-030..AC-035). */
describe('buildTaskSchema — closure feedback (D-7)', () => {
  const closing = values({ requires_closure_feedback: true, closure_feedback: null })

  it('requires the feedback when the flag is on and the status closes positively', () => {
    const result = buildTaskSchema(i18n.t, 'closed_positive').safeParse(closing)
    expect(issuePaths(result)).toContain('closure_feedback')
  })

  it('requires the feedback when the status closes negatively', () => {
    const result = buildTaskSchema(i18n.t, 'closed_negative').safeParse(closing)
    expect(issuePaths(result)).toContain('closure_feedback')
  })

  it('rejects a feedback made of whitespace only (AC-031)', () => {
    const result = buildTaskSchema(i18n.t, 'closed_positive').safeParse(
      values({ requires_closure_feedback: true, closure_feedback: '   ' }),
    )
    expect(issuePaths(result)).toContain('closure_feedback')
  })

  it('accepts a filled feedback (AC-032)', () => {
    const result = buildTaskSchema(i18n.t, 'closed_positive').safeParse(
      values({ requires_closure_feedback: true, closure_feedback: 'Consegnato al cliente' }),
    )
    expect(result.success).toBe(true)
  })

  it('does not require it when the flag is off (AC-033)', () => {
    const result = buildTaskSchema(i18n.t, 'closed_negative').safeParse(
      values({ requires_closure_feedback: false, closure_feedback: null }),
    )
    expect(result.success).toBe(true)
  })

  it('does not require it while no status is picked yet', () => {
    expect(buildTaskSchema(i18n.t, null).safeParse(closing).success).toBe(true)
  })

  it.each(['open', 'pending', 'in_validation'] as const)(
    'does not require it in the non-closing phase "%s"',
    (group) => {
      expect(buildTaskSchema(i18n.t, group).safeParse(closing).success).toBe(true)
    },
  )

})

/** Spec 0118 D-3/D-4: the initial status is derived server-side, never picked on create. */
describe('buildTaskSchema — create mode (spec 0118 D-1/D-3, isCreate=true)', () => {
  it('accepts a create submission with no status (AC-016 mirror): the server derives it', () => {
    const result = buildTaskSchema(i18n.t, null, true).safeParse(values({ task_status_id: null }))
    expect(result.success).toBe(true)
  })

  it('rejects a create submission missing requester, assignees and end date, one message each', () => {
    const result = buildTaskSchema(i18n.t, null, true).safeParse(
      values({ task_status_id: null, requester_id: null, assignee_ids: [], end_date: null }),
    )
    expect(issuePaths(result)).toEqual(
      expect.arrayContaining(['requester_id', 'assignee_ids', 'end_date']),
    )
    expect(issuePaths(result)).not.toContain('task_status_id')
  })

  it('still requires the status in edit mode (isCreate defaults to false)', () => {
    expect(issuePaths(buildTaskSchema(i18n.t).safeParse(values({ task_status_id: null })))).toContain(
      'task_status_id',
    )
  })
})

/** Spec 0118 D-9: retires AC-083 of spec 0101 ("assignee and watcher at once"). */
describe('buildTaskSchema — watcher overlap (spec 0118 D-9)', () => {
  it('rejects a watcher who is also the requester (AC-029 mirror)', () => {
    const result = buildTaskSchema(i18n.t).safeParse(values({ requester_id: 21, watcher_ids: [21] }))
    expect(issuePaths(result)).toContain('watcher_ids')
  })

  it('rejects a watcher who is also an assignee (AC-030/AC-032 mirror)', () => {
    const result = buildTaskSchema(i18n.t).safeParse(values({ assignee_ids: [31], watcher_ids: [31] }))
    expect(issuePaths(result)).toContain('watcher_ids')
  })

  it('accepts watchers disjoint from the requester and the assignees (AC-031/AC-034 mirror)', () => {
    expect(buildTaskSchema(i18n.t).safeParse(values()).success).toBe(true)
  })
})

describe('isClosingStatus — the rule branches on the phase, never on a label (AC-024)', () => {
  it('recognizes exactly the two closing phases', () => {
    expect(isClosingStatus('closed_positive')).toBe(true)
    expect(isClosingStatus('closed_negative')).toBe(true)
    expect(isClosingStatus('open')).toBe(false)
    expect(isClosingStatus('pending')).toBe(false)
    expect(isClosingStatus('in_validation')).toBe(false)
    expect(isClosingStatus(null)).toBe(false)
  })
})
