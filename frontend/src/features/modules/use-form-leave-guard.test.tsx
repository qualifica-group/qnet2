import { describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook } from '@testing-library/react'
import { ConfirmContext, type ConfirmFn } from '@/components/confirm-dialog-context'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'

/** Spec 0195 (user directive 2026-10-06): leaving a form being filled always asks first. */

const OPTIONS = { title: 'Leave?', description: 'Unsaved', confirmLabel: 'Leave', cancelLabel: 'Stay' }

function renderGuard(confirm: ConfirmFn | null) {
  const wrapper = ({ children }: { children: ReactNode }) => (
    <ConfirmContext.Provider value={confirm}>{children}</ConfirmContext.Provider>
  )
  return renderHook(() => useFormLeaveGuard(OPTIONS), { wrapper })
}

describe('useFormLeaveGuard', () => {
  it('asks before leaving and reports the answer', async () => {
    const confirm = vi.fn<ConfirmFn>().mockResolvedValue(false)
    const { result } = renderGuard(confirm)

    await expect(result.current.confirmLeave()).resolves.toBe(false)
    expect(confirm).toHaveBeenCalledWith(expect.objectContaining({ title: 'Leave?', confirmLabel: 'Leave' }))
  })

  it('does not ask twice once leaving was confirmed', async () => {
    const confirm = vi.fn<ConfirmFn>().mockResolvedValue(true)
    const { result } = renderGuard(confirm)

    await act(async () => {
      await result.current.confirmLeave()
    })
    await expect(result.current.confirmLeave()).resolves.toBe(true)
    expect(confirm).toHaveBeenCalledOnce()
  })

  it('lets a saved form leave unasked', async () => {
    const confirm = vi.fn<ConfirmFn>()
    const { result } = renderGuard(confirm)

    act(() => result.current.allowLeave())

    await expect(result.current.confirmLeave()).resolves.toBe(true)
    expect(confirm).not.toHaveBeenCalled()
  })

  it('holds a tab close/reload until leaving is allowed', () => {
    const { result } = renderGuard(vi.fn<ConfirmFn>())

    const blocked = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(blocked)
    expect(blocked.defaultPrevented).toBe(true)

    act(() => result.current.allowLeave())
    const allowed = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(allowed)
    expect(allowed.defaultPrevented).toBe(false)
  })
})
