import { describe, expect, it } from 'vitest'
import { resolveTaskStatusInterceptDecision } from '@/features/tasks/task-status-intercept-decision'

describe('resolveTaskStatusInterceptDecision (spec 0156 D-8)', () => {
  it('opens "Completa" when the picked status closes an open task', () => {
    expect(resolveTaskStatusInterceptDecision('open', 'closed_positive')).toBe('open_complete')
    expect(resolveTaskStatusInterceptDecision('pending', 'closed_negative')).toBe('open_complete')
    expect(resolveTaskStatusInterceptDecision('in_validation', 'closed_positive')).toBe('open_complete')
  })

  it('calls uncomplete when the picked status reopens a closed task', () => {
    expect(resolveTaskStatusInterceptDecision('closed_positive', 'open')).toBe('uncomplete')
    expect(resolveTaskStatusInterceptDecision('closed_negative', 'pending')).toBe('uncomplete')
  })

  it('does not intercept an open-to-open transition', () => {
    expect(resolveTaskStatusInterceptDecision('open', 'pending')).toBeNull()
    expect(resolveTaskStatusInterceptDecision('pending', 'in_validation')).toBeNull()
  })

  it('does not intercept a closed-to-closed transition (e.g. a super-admin override)', () => {
    expect(resolveTaskStatusInterceptDecision('closed_positive', 'closed_negative')).toBeNull()
  })

  it('treats an unknown group (lookup not yet resolved) as not-closing', () => {
    expect(resolveTaskStatusInterceptDecision('open', undefined)).toBeNull()
    expect(resolveTaskStatusInterceptDecision(undefined, 'closed_positive')).toBe('open_complete')
  })
})
