import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildTaskSchema, MAX_TASK_FORM_SUBTASKS } from '@/features/tasks/task-schema'
import { taskFormValues as values, taskRecurrenceFormValues as recurrence } from '@/features/tasks/task-fixtures'

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

/** Spec 0118 D-3/D-4: the initial status is derived server-side, never picked on create. */
describe('buildTaskSchema — create mode (spec 0118 D-1/D-3, isCreate=true)', () => {
  it('accepts a create submission with no status (AC-016 mirror): the server derives it', () => {
    const result = buildTaskSchema(i18n.t, true).safeParse(values({ task_status_id: null }))
    expect(result.success).toBe(true)
  })

  it('rejects a create submission missing requester, assignees and end date, one message each', () => {
    const result = buildTaskSchema(i18n.t, true).safeParse(
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

/** Spec 0120 D-1/AC-033: every rule below is SKIPPED while `recurrence.enabled` is false. */
describe('buildTaskSchema — recurrence (spec 0120 D-1/AC-033)', () => {
  it('ignores a garbage recurrence slice while disabled', () => {
    const result = buildTaskSchema(i18n.t).safeParse(
      values({ recurrence: recurrence({ interval: 0, ends: 'on_date' }) }),
    )
    expect(result.success).toBe(true)
  })

  it('rejects an interval below 1 once enabled', () => {
    const result = buildTaskSchema(i18n.t).safeParse(
      values({ recurrence: recurrence({ enabled: true, frequency: 'daily', ends: 'never', interval: 0 }) }),
    )
    expect(issuePaths(result)).toContain('recurrence.interval')
  })

  it('rejects a weekly rule with no weekday picked', () => {
    const result = buildTaskSchema(i18n.t).safeParse(
      values({ recurrence: recurrence({ enabled: true, frequency: 'weekly', ends: 'never', weekdays: [] }) }),
    )
    expect(issuePaths(result)).toContain('recurrence.weekdays')
  })

  it('accepts a weekly rule once at least one weekday is picked', () => {
    const result = buildTaskSchema(i18n.t).safeParse(
      values({ recurrence: recurrence({ enabled: true, frequency: 'weekly', ends: 'never', weekdays: [1, 3] }) }),
    )
    expect(result.success).toBe(true)
  })

  it('rejects a monthly rule with a day outside 1..31', () => {
    const result = buildTaskSchema(i18n.t).safeParse(
      values({ recurrence: recurrence({ enabled: true, frequency: 'monthly', ends: 'never', month_day: 32 }) }),
    )
    expect(issuePaths(result)).toContain('recurrence.month_day')
  })

  it('accepts a monthly rule with a day within 1..31', () => {
    const result = buildTaskSchema(i18n.t).safeParse(
      values({ recurrence: recurrence({ enabled: true, frequency: 'monthly', ends: 'never', month_day: 31 }) }),
    )
    expect(result.success).toBe(true)
  })

  it('rejects `ends: on_date` with no `ends_on`', () => {
    const result = buildTaskSchema(i18n.t).safeParse(
      values({ recurrence: recurrence({ enabled: true, frequency: 'daily', ends: 'on_date', ends_on: null }) }),
    )
    expect(issuePaths(result)).toContain('recurrence.ends_on')
  })

  it('rejects an `ends_on` not strictly after the task end_date (`after:end_date`)', () => {
    const result = buildTaskSchema(i18n.t).safeParse(
      values({
        end_date: '2026-09-05',
        recurrence: recurrence({ enabled: true, frequency: 'daily', ends: 'on_date', ends_on: '2026-09-05' }),
      }),
    )
    expect(issuePaths(result)).toContain('recurrence.ends_on')
  })

  it('accepts an `ends_on` strictly after the task end_date', () => {
    const result = buildTaskSchema(i18n.t).safeParse(
      values({
        end_date: '2026-09-05',
        recurrence: recurrence({ enabled: true, frequency: 'daily', ends: 'on_date', ends_on: '2026-09-06' }),
      }),
    )
    expect(result.success).toBe(true)
  })

  it('rejects `ends: after_count` with no (or a zero) `occurrence_count`', () => {
    const result = buildTaskSchema(i18n.t).safeParse(
      values({
        recurrence: recurrence({ enabled: true, frequency: 'daily', ends: 'after_count', occurrence_count: 0 }),
      }),
    )
    expect(issuePaths(result)).toContain('recurrence.occurrence_count')
  })

  it('accepts `ends: after_count` with a positive `occurrence_count`', () => {
    const result = buildTaskSchema(i18n.t).safeParse(
      values({
        recurrence: recurrence({ enabled: true, frequency: 'daily', ends: 'after_count', occurrence_count: 5 }),
      }),
    )
    expect(result.success).toBe(true)
  })
})

/** Spec 0155 D-1: the ordinal/yearly branches, and `custom` needing nothing beyond `interval`. */
describe('buildTaskSchema — recurrence, spec 0155 D-1', () => {
  it('accepts a custom rule with only interval/ends set', () => {
    const result = buildTaskSchema(i18n.t).safeParse(
      values({ recurrence: recurrence({ enabled: true, frequency: 'custom', ends: 'never', interval: 3 }) }),
    )
    expect(result.success).toBe(true)
  })

  it('treats a missing month_mode as fixed: a monthly rule with only month_day set is valid', () => {
    const result = buildTaskSchema(i18n.t).safeParse(
      values({
        recurrence: recurrence({ enabled: true, frequency: 'monthly', ends: 'never', month_mode: null, month_day: 15 }),
      }),
    )
    expect(result.success).toBe(true)
  })

  it('rejects a monthly ordinal rule missing ordinal/ordinal_weekday', () => {
    const result = buildTaskSchema(i18n.t).safeParse(
      values({
        recurrence: recurrence({ enabled: true, frequency: 'monthly', ends: 'never', month_mode: 'ordinal' }),
      }),
    )
    expect(issuePaths(result)).toContain('recurrence.ordinal')
    expect(issuePaths(result)).toContain('recurrence.ordinal_weekday')
  })

  it('accepts a monthly ordinal rule with ordinal/ordinal_weekday set', () => {
    const result = buildTaskSchema(i18n.t).safeParse(
      values({
        recurrence: recurrence({
          enabled: true,
          frequency: 'monthly',
          ends: 'never',
          month_mode: 'ordinal',
          ordinal: 2,
          ordinal_weekday: 2,
        }),
      }),
    )
    expect(result.success).toBe(true)
  })

  it('rejects a yearly rule with no year_month', () => {
    const result = buildTaskSchema(i18n.t).safeParse(
      values({
        recurrence: recurrence({ enabled: true, frequency: 'yearly', ends: 'never', month_day: 5, year_month: null }),
      }),
    )
    expect(issuePaths(result)).toContain('recurrence.year_month')
  })

  it('accepts a yearly rule with month_day and year_month set', () => {
    const result = buildTaskSchema(i18n.t).safeParse(
      values({
        recurrence: recurrence({ enabled: true, frequency: 'yearly', ends: 'never', month_day: 5, year_month: 3 }),
      }),
    )
    expect(result.success).toBe(true)
  })
})

/** Spec 0155 D-3: the create-only "Sottotask" block, title required per row. */
describe('buildTaskSchema — subtasks, spec 0155 D-3', () => {
  it('accepts an empty subtasks list', () => {
    expect(buildTaskSchema(i18n.t, true).safeParse(values({ subtasks: [] })).success).toBe(true)
  })

  it('rejects a row with a blank title, at its own index', () => {
    const result = buildTaskSchema(i18n.t, true).safeParse(
      values({
        subtasks: [
          { title: 'Prepara il preventivo', end_date: null, assignee_ids: [], subtasks: [] },
          { title: '  ', end_date: null, assignee_ids: [], subtasks: [] },
        ],
      }),
    )
    expect(issuePaths(result)).toContain('subtasks.1.title')
  })

  it('accepts every row once every title is filled', () => {
    const result = buildTaskSchema(i18n.t, true).safeParse(
      values({
        subtasks: [{ title: 'Prepara il preventivo', end_date: '2026-09-10', assignee_ids: [31], subtasks: [] }],
      }),
    )
    expect(result.success).toBe(true)
  })
})

/** Spec 0161 D-1: 3-level tree (figlio/nipote/pronipote), 50-node cap counted across the whole tree. */
describe('buildTaskSchema — nested subtasks, spec 0161 D-1', () => {
  it('accepts a 3-level tree', () => {
    const result = buildTaskSchema(i18n.t, true).safeParse(
      values({
        subtasks: [
          {
            title: 'Figlio',
            end_date: null,
            assignee_ids: [],
            subtasks: [
              {
                title: 'Nipote',
                end_date: null,
                assignee_ids: [],
                subtasks: [{ title: 'Pronipote', end_date: null, assignee_ids: [] }],
              },
            ],
          },
        ],
      }),
    )
    expect(result.success).toBe(true)
  })

  it('rejects a blank title at the 3rd level, at its own nested path', () => {
    const result = buildTaskSchema(i18n.t, true).safeParse(
      values({
        subtasks: [
          {
            title: 'Figlio',
            end_date: null,
            assignee_ids: [],
            subtasks: [
              {
                title: 'Nipote',
                end_date: null,
                assignee_ids: [],
                subtasks: [{ title: '  ', end_date: null, assignee_ids: [] }],
              },
            ],
          },
        ],
      }),
    )
    expect(issuePaths(result)).toContain('subtasks.0.subtasks.0.subtasks.0.title')
  })

  it('rejects a tree of 51 nodes spread across levels, with a clear message', () => {
    const grandchildren = Array.from({ length: MAX_TASK_FORM_SUBTASKS }, (_unused, index) => ({
      title: `Nipote ${index}`,
      end_date: null,
      assignee_ids: [] as number[],
      subtasks: [],
    }))
    const result = buildTaskSchema(i18n.t, true).safeParse(
      values({
        subtasks: [{ title: 'Figlio', end_date: null, assignee_ids: [], subtasks: grandchildren }],
      }),
    )
    expect(result.success).toBe(false)
    const issue = result.success
      ? undefined
      : result.error.issues.find((candidate) => candidate.path.join('.') === 'subtasks')
    expect(issue?.message).toBeTruthy()
  })

  it('accepts exactly 50 nodes across the tree', () => {
    const grandchildren = Array.from({ length: MAX_TASK_FORM_SUBTASKS - 1 }, (_unused, index) => ({
      title: `Nipote ${index}`,
      end_date: null,
      assignee_ids: [] as number[],
      subtasks: [],
    }))
    const result = buildTaskSchema(i18n.t, true).safeParse(
      values({
        subtasks: [{ title: 'Figlio', end_date: null, assignee_ids: [], subtasks: grandchildren }],
      }),
    )
    expect(result.success).toBe(true)
  })
})
