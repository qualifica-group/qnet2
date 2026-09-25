import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { useTimeEntryForm } from '@/features/time-entries/form/use-time-entry-form'

/**
 * Spec 0163 D-1: `work_order_stage_id`'s three reset handlers — split out of
 * `time-entry-form-body.test.tsx` (engineering.md §6), mirrors
 * `use-task-form-work-order-stage.test.tsx`'s own hook-level split.
 */

vi.mock('@/features/time-entries/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/time-entries/api')>(
    '@/features/time-entries/api',
  )
  return { ...actual, createTimeEntry: vi.fn(), updateTimeEntry: vi.fn() }
})

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, fetchTask: vi.fn() }
})

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
})

describe('useTimeEntryForm — work_order_stage_id reset handlers (spec 0163 D-1)', () => {
  it('clears the fase when a commessa is picked', () => {
    const { result } = renderHook(
      () => useTimeEntryForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => result.current.form.setValue('work_order_stage_id', 7))
    expect(result.current.form.getValues('work_order_stage_id')).toBe(7)

    act(() => result.current.handleWorkOrderItemChange({ id: 30, label: 'COM-0001' }))

    expect(result.current.form.getValues('work_order_stage_id')).toBeNull()
  })

  it('clears the fase when the commessa is cleared', () => {
    const { result } = renderHook(
      () => useTimeEntryForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => result.current.form.setValue('work_order_stage_id', 7))
    act(() => result.current.handleWorkOrderItemChange(null))

    expect(result.current.form.getValues('work_order_stage_id')).toBeNull()
  })

  it('clears the fase when the opportunita\' is picked (mutually exclusive commessa)', () => {
    const { result } = renderHook(
      () => useTimeEntryForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => result.current.form.setValue('work_order_stage_id', 7))
    act(() => result.current.handleOpportunityItemChange({ id: 20, label: 'Opportunita\' X' }))

    expect(result.current.form.getValues('work_order_stage_id')).toBeNull()
  })

  it('clears the fase when the cliente changes', () => {
    const { result } = renderHook(
      () => useTimeEntryForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => result.current.form.setValue('work_order_stage_id', 7))
    act(() => result.current.handleRegistryChange())

    expect(result.current.form.getValues('work_order_stage_id')).toBeNull()
  })
})
