import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { useTimeEntryRowMutations } from '@/features/time-entries/days/use-time-entry-row-mutations'
import type { TimeEntry } from '@/features/time-entries/types'

const updateTimeEntryMock = vi.fn()
const deleteTimeEntryMock = vi.fn()

vi.mock('@/features/time-entries/api', () => ({
  updateTimeEntry: (...args: unknown[]) => updateTimeEntryMock(...args),
  deleteTimeEntry: (...args: unknown[]) => deleteTimeEntryMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function entry(overrides: Partial<TimeEntry> = {}): TimeEntry {
  return {
    id: 42,
    user: { id: 1, name: 'Mario' },
    date: '2026-09-14',
    title: 'Chiamata cliente',
    task_type: { id: 3, name: 'Chiamata', color: 'blue', icon: 'phone' },
    start_time: '09:00',
    end_time: '09:30',
    minutes: 30,
    notes: 'Nota',
    registry: { id: 5, name: 'ACME' },
    opportunity: null,
    work_order: { id: 9, code: 'WO-1', title: 'Commessa' },
    task: null,
    created_at: '',
    updated_at: '',
    permissions: { update: true, delete: true },
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateTimeEntryMock.mockReset()
  deleteTimeEntryMock.mockReset()
  vi.mocked(toast.success).mockClear()
  vi.mocked(toast.error).mockClear()
})

describe('useTimeEntryRowMutations', () => {
  it('changes type: PUTs the full entry payload with the new task_type_id', async () => {
    updateTimeEntryMock.mockResolvedValue(entry({ task_type: { id: 4, name: 'Ticket', color: 'red', icon: null } }))
    const { result } = renderHook(() => useTimeEntryRowMutations(), { wrapper: wrapper() })

    result.current.changeType(entry(), 4)

    await waitFor(() => expect(updateTimeEntryMock).toHaveBeenCalledTimes(1))
    expect(updateTimeEntryMock).toHaveBeenCalledWith(42, {
      date: '2026-09-14',
      title: 'Chiamata cliente',
      task_type_id: 4,
      start_time: '09:00',
      end_time: '09:30',
      minutes: 30,
      notes: 'Nota',
      registry_id: 5,
      opportunity_id: null,
      work_order_id: 9,
      task_id: null,
    })
    await waitFor(() => expect(toast.success).toHaveBeenCalled())
  })

  it('is a no-op when the selected type equals the current one (D-14)', () => {
    const { result } = renderHook(() => useTimeEntryRowMutations(), { wrapper: wrapper() })

    result.current.changeType(entry({ task_type: { id: 3, name: 'Chiamata', color: 'blue', icon: 'phone' } }), 3)

    expect(updateTimeEntryMock).not.toHaveBeenCalled()
  })

  it('deletes an entry by id and toasts success', async () => {
    deleteTimeEntryMock.mockResolvedValue(undefined)
    const { result } = renderHook(() => useTimeEntryRowMutations(), { wrapper: wrapper() })

    result.current.deleteEntry(42)

    await waitFor(() => expect(deleteTimeEntryMock).toHaveBeenCalledWith(42))
    await waitFor(() => expect(toast.success).toHaveBeenCalled())
  })

  it('toasts an error when the delete request fails', async () => {
    deleteTimeEntryMock.mockRejectedValue(new Error('network'))
    const { result } = renderHook(() => useTimeEntryRowMutations(), { wrapper: wrapper() })

    result.current.deleteEntry(42)

    await waitFor(() => expect(toast.error).toHaveBeenCalled())
  })
})
