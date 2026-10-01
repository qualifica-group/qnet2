import { describe, expect, it, vi } from 'vitest'
import { useQuery } from '@tanstack/react-query'
import { SYSTEM_HEALTH_REFETCH_MS, useSystemHealth } from '@/features/system-health/use-system-health'
import { systemHealthKeys } from '@/features/system-health/query-keys'

vi.mock('@tanstack/react-query', () => ({
  useQuery: vi.fn(() => ({ data: undefined })),
}))

describe('useSystemHealth', () => {
  it('configures refetchInterval at 30000ms (AC-014)', () => {
    useSystemHealth()

    expect(SYSTEM_HEALTH_REFETCH_MS).toBe(30000)
    expect(vi.mocked(useQuery)).toHaveBeenCalledWith(
      expect.objectContaining({ queryKey: systemHealthKeys.all, refetchInterval: 30000 }),
    )
  })
})
