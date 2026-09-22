import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fetchWorkOrderStages } from '@/features/work-orders/task-board/api'
import { useTaskWorkOrderStageOptions } from '@/features/tasks/use-task-work-order-stage-options'
import type { WorkOrderStage } from '@/features/work-orders/task-board/types'

/**
 * Spec 0146 D-3/AC-030: the "Fase" select lists a commessa's OPEN fasi, plus
 * the task's own persisted fase even when it has since closed (so an edit
 * form never strands on a value it can no longer resubmit unchanged).
 */

vi.mock('@/features/work-orders/task-board/api', () => ({ fetchWorkOrderStages: vi.fn() }))

function stage(overrides: Partial<WorkOrderStage> = {}): WorkOrderStage {
  return { id: 1, name: 'Analisi', sort_order: 0, closed_at: null, closed_by: null, ...overrides }
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeEach(() => {
  vi.mocked(fetchWorkOrderStages).mockReset()
})

describe('useTaskWorkOrderStageOptions (spec 0146 D-3/AC-030)', () => {
  it('does not query when there is no commessa', () => {
    renderHook(() => useTaskWorkOrderStageOptions(null, null), { wrapper: wrapper() })

    expect(fetchWorkOrderStages).not.toHaveBeenCalled()
  })

  it('lists only the OPEN fasi', async () => {
    vi.mocked(fetchWorkOrderStages).mockResolvedValue([
      stage({ id: 1, name: 'Analisi', closed_at: null }),
      stage({ id: 2, name: 'Chiusa', closed_at: '2026-09-01T00:00:00Z' }),
    ])

    const { result } = renderHook(() => useTaskWorkOrderStageOptions(9, null), { wrapper: wrapper() })

    await waitFor(() => expect(result.current.options).toEqual([{ id: 1, name: 'Analisi' }]))
  })

  it('keeps the persisted current fase selectable even once closed', async () => {
    vi.mocked(fetchWorkOrderStages).mockResolvedValue([stage({ id: 1, name: 'Analisi', closed_at: null })])

    const { result } = renderHook(
      () => useTaskWorkOrderStageOptions(9, { id: 2, name: 'Chiusa' }),
      { wrapper: wrapper() },
    )

    await waitFor(() =>
      expect(result.current.options).toEqual([
        { id: 1, name: 'Analisi' },
        { id: 2, name: 'Chiusa' },
      ]),
    )
  })

  it('does not duplicate the current fase when it is already open', async () => {
    vi.mocked(fetchWorkOrderStages).mockResolvedValue([stage({ id: 1, name: 'Analisi', closed_at: null })])

    const { result } = renderHook(
      () => useTaskWorkOrderStageOptions(9, { id: 1, name: 'Analisi' }),
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(result.current.options).toEqual([{ id: 1, name: 'Analisi' }]))
  })
})
