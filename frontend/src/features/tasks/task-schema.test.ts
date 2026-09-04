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

  it('rejects an unpicked status (`task_status_id` is NOT NULL server-side)', () => {
    expect(issuePaths(buildTaskSchema(i18n.t).safeParse(values({ task_status_id: null })))).toContain(
      'task_status_id',
    )
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

  it('does not require it on a CUSTOM status, which belongs to no phase (AC-034/D-5)', () => {
    expect(buildTaskSchema(i18n.t, null).safeParse(closing).success).toBe(true)
  })

  it('does not require it on a non-closing system status', () => {
    expect(buildTaskSchema(i18n.t, 'in_validation').safeParse(closing).success).toBe(true)
  })
})

describe('isClosingStatus — the rule branches on the system key, never on a label (AC-024)', () => {
  it('recognizes exactly the two closing keys', () => {
    expect(isClosingStatus('closed_positive')).toBe(true)
    expect(isClosingStatus('closed_negative')).toBe(true)
    expect(isClosingStatus('open')).toBe(false)
    expect(isClosingStatus('in_progress')).toBe(false)
    expect(isClosingStatus('pending')).toBe(false)
    expect(isClosingStatus('in_validation')).toBe(false)
    expect(isClosingStatus(null)).toBe(false)
  })
})
