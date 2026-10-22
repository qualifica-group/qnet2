import { beforeEach, describe, expect, it, vi } from 'vitest'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { useAbsenceScope } from '@/features/absences/requests/use-absence-scope'
import { absenceKeys } from '@/features/absences/query-keys'
import type { AbsenceSummary } from '@/features/absences/types'

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

const summaryMock = vi.fn()
vi.mock('@/features/absences/api', () => ({ getAbsenceSummary: () => summaryMock() }))

const summary = (toApprove: number): AbsenceSummary => ({
  to_approve_count: toApprove,
  my_pending_count: 0,
  daily_minutes: 480,
  my_upcoming: [],
  absent_today: [],
})

function setup() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>
  return { client, ...renderHook(() => useAbsenceScope(), { wrapper }) }
}

beforeEach(() => vi.clearAllMocks())

describe('useAbsenceScope (spec 0304 AC-020)', () => {
  it('opens on "To approve" when the first summary has requests to decide', async () => {
    summaryMock.mockResolvedValue(summary(2))
    const { result } = setup()

    expect(result.current.scope).toBe('mine')
    await waitFor(() => expect(result.current.scope).toBe('to_approve'))
    expect(result.current.toApproveCount).toBe(2)
  })

  it('stays on "Mine" when there is nothing to decide', async () => {
    summaryMock.mockResolvedValue(summary(0))
    const { result } = setup()

    await waitFor(() => expect(summaryMock).toHaveBeenCalled())
    await waitFor(() => expect(result.current.toApproveCount).toBe(0))
    expect(result.current.scope).toBe('mine')
  })

  it('never moves the tab on a later refetch', async () => {
    summaryMock.mockResolvedValue(summary(0))
    const { result, client } = setup()
    await waitFor(() => expect(summaryMock).toHaveBeenCalledTimes(1))

    act(() => result.current.selectScope('all'))
    summaryMock.mockResolvedValue(summary(5))
    await act(() => client.invalidateQueries({ queryKey: absenceKeys.summary }))

    await waitFor(() => expect(result.current.toApproveCount).toBe(5))
    expect(result.current.scope).toBe('all')
  })
})
