import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, renderHook, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { useOutboundEmailsTab } from '@/features/outbound-emails/use-outbound-emails-tab'

/**
 * Spec 0175 AC-019: the "Email" tab is gated on `view_emails` alone; `send_email`
 * only decides whether the panel offers "Nuova email" (covered in
 * `outbound-emails-panel.test.tsx`, which owns the panel's own behaviour).
 */
const panelMock = vi.fn()
vi.mock('@/features/outbound-emails/outbound-emails-panel', () => ({
  OutboundEmailsPanel: (props: { owner: { type: string; id: number }; canSend: boolean }) => {
    panelMock(props)
    return <div>{`panel:${props.owner.id}:${props.canSend}`}</div>
  },
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('useOutboundEmailsTab', () => {
  it('is null without view_emails, whatever send_email is', () => {
    expect(renderHook(() => useOutboundEmailsTab({ type: 'work-orders', id: 1 }, false, true)).result.current).toBeNull()
    expect(renderHook(() => useOutboundEmailsTab({ type: 'work-orders', id: 1 }, false, false)).result.current).toBeNull()
  })

  it('mounts the panel with view_emails alone, forwarding send_email as canSend', () => {
    const { result } = renderHook(() => useOutboundEmailsTab({ type: 'work-orders', id: 7 }, true, false))

    expect(result.current?.value).toBe('work-order-emails')
    expect(result.current?.label).toBe('Email')

    render(<>{result.current?.content}</>)
    expect(screen.getByText('panel:7:false')).toBeInTheDocument()
    expect(panelMock).toHaveBeenCalledWith(expect.objectContaining({ owner: { type: 'work-orders', id: 7 }, canSend: false }))
  })
})
