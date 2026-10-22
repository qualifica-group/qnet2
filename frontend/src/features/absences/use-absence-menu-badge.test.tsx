import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import i18n from '@/i18n'
import { useAbsenceMenuBadge } from '@/features/absences/use-absence-menu-badge'

let allowed = true
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => allowed, hasRole: () => false, roles: [], isLoading: false }),
}))

const summaryMock = vi.fn()
vi.mock('@/features/absences/api', () => ({ getAbsenceSummary: () => summaryMock() }))

function setup() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>
  return renderHook(() => useAbsenceMenuBadge(), { wrapper })
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  vi.clearAllMocks()
  allowed = true
})

describe('useAbsenceMenuBadge (spec 0304 AC-020)', () => {
  it('carries the count to approve with an accessible label of the exact number', async () => {
    summaryMock.mockResolvedValue({ to_approve_count: 4 })
    const { result } = setup()

    await waitFor(() => expect(result.current).not.toBeNull())
    expect(result.current).toEqual({ count: 4, label: '4', ariaLabel: '4 richieste da approvare' })
  })

  it('caps the visible text at 99+', async () => {
    summaryMock.mockResolvedValue({ to_approve_count: 150 })
    const { result } = setup()

    await waitFor(() => expect(result.current?.label).toBe('99+'))
    expect(result.current?.ariaLabel).toBe('150 richieste da approvare')
  })

  it('is absent with a zero count', async () => {
    summaryMock.mockResolvedValue({ to_approve_count: 0 })
    const { result } = setup()

    await waitFor(() => expect(summaryMock).toHaveBeenCalled())
    expect(result.current).toBeNull()
  })

  it('makes no call without the viewAny permission', () => {
    allowed = false
    const { result } = setup()

    expect(summaryMock).not.toHaveBeenCalled()
    expect(result.current).toBeNull()
  })
})
