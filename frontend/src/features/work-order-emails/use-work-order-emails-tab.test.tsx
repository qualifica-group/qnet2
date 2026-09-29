import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, renderHook, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { useWorkOrderEmailsTab } from '@/features/work-order-emails/use-work-order-emails-tab'

/**
 * Spec 0175 AC-019: the "Email" tab is gated on `view_emails` alone; `send_email`
 * only decides whether the panel offers "Nuova email" (covered in
 * `work-order-emails-panel.test.tsx`, which owns the panel's own behaviour).
 */
const panelMock = vi.fn()
vi.mock('@/features/work-order-emails/work-order-emails-panel', () => ({
  WorkOrderEmailsPanel: (props: { workOrderId: number; canSend: boolean }) => {
    panelMock(props)
    return <div>{`panel:${props.workOrderId}:${props.canSend}`}</div>
  },
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('useWorkOrderEmailsTab', () => {
  it('is null without view_emails, whatever send_email is', () => {
    expect(renderHook(() => useWorkOrderEmailsTab(1, false, true)).result.current).toBeNull()
    expect(renderHook(() => useWorkOrderEmailsTab(1, false, false)).result.current).toBeNull()
  })

  it('mounts the panel with view_emails alone, forwarding send_email as canSend', () => {
    const { result } = renderHook(() => useWorkOrderEmailsTab(7, true, false))

    expect(result.current?.value).toBe('work-order-emails')
    expect(result.current?.label).toBe('Email')

    render(<>{result.current?.content}</>)
    expect(screen.getByText('panel:7:false')).toBeInTheDocument()
    expect(panelMock).toHaveBeenCalledWith(expect.objectContaining({ workOrderId: 7, canSend: false }))
  })
})
