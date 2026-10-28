import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useDefaultStatuses } from '@/features/work-order-workflows/use-default-statuses'
import type { WorkOrderWorkflowStatusItem } from '@/features/work-order-workflows/types'

const fetchDefaultStatusesMock = vi.fn()
const updateDefaultStatusesMock = vi.fn()

vi.mock('@/features/work-order-workflows/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/work-order-workflows/api')>()),
  fetchDefaultStatuses: (...args: unknown[]) => fetchDefaultStatusesMock(...args),
  updateDefaultStatuses: (...args: unknown[]) => updateDefaultStatusesMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const LABELS = { saved: 'saved', forbidden: 'forbidden', genericError: 'error', nameRequired: 'name required' }

function status(overrides: Partial<WorkOrderWorkflowStatusItem> & Pick<WorkOrderWorkflowStatusItem, 'id' | 'name'>): WorkOrderWorkflowStatusItem {
  return { description: null, color: null, sort_order: 0, system_key: null, group: 'in_progress', progress_percentage: null, ...overrides }
}

const SET: WorkOrderWorkflowStatusItem[] = [
  status({ id: 1, name: 'Open', system_key: 'open', group: 'open' }),
  status({ id: 2, name: 'Completed', system_key: 'completed', group: 'completed', progress_percentage: 100 }),
  status({ id: 3, name: 'Closed', system_key: 'closed', group: 'closed' }),
]

function renderState() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>
  return renderHook(() => useDefaultStatuses({ enabled: true, labels: LABELS }), { wrapper })
}

beforeEach(() => {
  fetchDefaultStatusesMock.mockReset().mockResolvedValue(SET)
  updateDefaultStatusesMock.mockReset()
})

describe('useDefaultStatuses (work orders)', () => {
  it('adds a custom row before the pinned tail and saves the set with ids and percentages', async () => {
    updateDefaultStatusesMock.mockResolvedValue(SET)
    const { result } = renderState()
    await waitFor(() => expect(result.current.rows).toHaveLength(3))

    act(() => result.current.addCustom())
    const added = result.current.rows[1]
    expect(added.group).toBe('in_progress')
    expect(result.current.rows.map((row) => row.system_key)).toEqual(['open', null, 'completed', 'closed'])

    act(() => result.current.updateRow(added.id, { name: 'Review', progress_percentage: 40 }))
    await act(async () => {
      await result.current.save()
    })

    const [payload] = updateDefaultStatusesMock.mock.calls[0]
    expect(payload.statuses.map((row: { id?: number; name: string; progress_percentage: number | null }) => [row.id, row.name, row.progress_percentage])).toEqual([
      [1, 'Open', null],
      [undefined, 'Review', 40],
      [2, 'Completed', 100],
      [3, 'Closed', null],
    ])
  })

  it('refuses to save while a custom row has no name', async () => {
    const { result } = renderState()
    await waitFor(() => expect(result.current.rows).toHaveLength(3))

    act(() => result.current.addCustom())
    let saved = true
    await act(async () => {
      saved = await result.current.save()
    })

    expect(saved).toBe(false)
    expect(result.current.error).toBe('name required')
    expect(updateDefaultStatusesMock).not.toHaveBeenCalled()
  })
})
