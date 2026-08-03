import { describe, expect, it } from 'vitest'
import { markValidatedRow } from '@/features/opportunity-workflows/workflow-status-rows'
import type { WorkflowStatusFormRow } from '@/features/opportunity-workflows/types'

/**
 * The OPTIONAL `validated` mark (user directive 2026-08-03): at most one row
 * per set carries it, none does by default, and the marked row sits where the
 * backend persists it — right before the pinned closed rows.
 */
function row(id: string, overrides: Partial<WorkflowStatusFormRow> = {}): WorkflowStatusFormRow {
  return {
    id,
    name: id,
    description: null,
    color: null,
    group: 'pending',
    system_key: null,
    requires_note: false,
    ...overrides,
  }
}

const ROWS: WorkflowStatusFormRow[] = [
  row('open', { group: 'open', system_key: 'open' }),
  row('a'),
  row('b'),
  row('won', { group: 'closed_won', system_key: 'closed_won' }),
  row('lost', { group: 'closed_lost', system_key: 'closed_lost' }),
]

describe('markValidatedRow', () => {
  it('marks a custom row and moves it right before the pinned closed rows', () => {
    const next = markValidatedRow(ROWS, 'a', true)

    expect(next.map((r) => r.id)).toEqual(['open', 'b', 'a', 'won', 'lost'])
    expect(next.find((r) => r.id === 'a')).toMatchObject({ system_key: 'validated', group: 'validated' })
  })

  it('releases the previous mark when another row takes it', () => {
    const marked = markValidatedRow(ROWS, 'a', true)
    const next = markValidatedRow(marked, 'b', true)

    expect(next.filter((r) => r.system_key === 'validated').map((r) => r.id)).toEqual(['b'])
    expect(next.find((r) => r.id === 'a')).toMatchObject({ system_key: null, group: 'pending' })
  })

  it('unmarks back to a custom row placed after the other customs', () => {
    const next = markValidatedRow(markValidatedRow(ROWS, 'a', true), 'a', false)

    expect(next.map((r) => r.id)).toEqual(['open', 'b', 'a', 'won', 'lost'])
    expect(next.find((r) => r.id === 'a')).toMatchObject({ system_key: null, group: 'pending' })
  })

  it('never touches a mandatory system row', () => {
    expect(markValidatedRow(ROWS, 'won', true)).toBe(ROWS)
    expect(markValidatedRow(ROWS, 'open', true)).toBe(ROWS)
  })
})
