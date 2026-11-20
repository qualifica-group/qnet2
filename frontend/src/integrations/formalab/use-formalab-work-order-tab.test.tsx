import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHook, waitFor } from '@testing-library/react'
import { buildLink, buildWorkOrderLink, createWrapper } from '@/integrations/formalab/test-support'
import { useFormalabWorkOrderTab } from '@/integrations/formalab/use-formalab-work-order-tab'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

const fetchLinkMock = vi.fn()
vi.mock('@/integrations/formalab/api', () => ({
  fetchFormalabWorkOrderLink: (id: number) => fetchLinkMock(id),
}))

const workOrder = { id: 7 } as WorkOrderDetailWithPermissions

describe('useFormalabWorkOrderTab', () => {
  beforeEach(() => {
    fetchLinkMock.mockReset()
  })

  it('returns no tab and does not fetch when the integration is inactive', () => {
    const { Wrapper } = createWrapper()

    const { result } = renderHook(() => useFormalabWorkOrderTab({ workOrder, enabled: false }), { wrapper: Wrapper })

    expect(result.current).toEqual([])
    expect(fetchLinkMock).not.toHaveBeenCalled()
  })

  it('returns the tab when active and the work order is eligible', async () => {
    fetchLinkMock.mockResolvedValue(buildWorkOrderLink({ eligible: true }))
    const { Wrapper } = createWrapper()

    const { result } = renderHook(() => useFormalabWorkOrderTab({ workOrder, enabled: true }), { wrapper: Wrapper })

    await waitFor(() => expect(result.current).toHaveLength(1))
    expect(result.current[0].value).toBe('formalab')
    expect(fetchLinkMock).toHaveBeenCalledWith(7)
  })

  it('returns the tab when not eligible but already linked', async () => {
    fetchLinkMock.mockResolvedValue(buildWorkOrderLink({ eligible: false, link: buildLink() }))
    const { Wrapper } = createWrapper()

    const { result } = renderHook(() => useFormalabWorkOrderTab({ workOrder, enabled: true }), { wrapper: Wrapper })

    await waitFor(() => expect(result.current).toHaveLength(1))
  })

  it('returns no tab when active but neither eligible nor linked', async () => {
    fetchLinkMock.mockResolvedValue(buildWorkOrderLink({ eligible: false, link: null }))
    const { Wrapper } = createWrapper()

    const { result } = renderHook(() => useFormalabWorkOrderTab({ workOrder, enabled: true }), { wrapper: Wrapper })

    await waitFor(() => expect(fetchLinkMock).toHaveBeenCalled())
    expect(result.current).toEqual([])
  })
})
