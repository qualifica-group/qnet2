import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import i18n from '@/i18n'
import { useChatDock } from '@/features/work-order-chat/dock/chat-dock-context'
import { ChatDockProvider } from '@/features/work-order-chat/dock/chat-dock-provider'
import type { ChatChannel } from '@/features/work-order-chat/types'
import { WorkOrderChatTabButton } from '@/features/work-order-chat/work-order-chat-tab-button'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/**
 * "Chat" in the work order's tab strip (user directive 2026-11-25): the unread
 * dot of its channel, and a click that opens the conversation in the dock.
 */

const channelsMock = vi.fn<() => Promise<ChatChannel[]>>()
vi.mock('@/features/work-order-chat/api', () => ({ fetchChatChannels: () => channelsMock() }))
vi.mock('@/hooks/use-mobile', () => ({ useIsMobile: () => false }))

const WORK_ORDER = {
  id: 4,
  code: 'COM-0004',
  title: 'Impianto',
  registry: { id: 1, name: 'Acme S.p.A.' },
  permissions: { resource: {}, fields: {}, actions: { view_chat: true, write_chat: true } },
} as unknown as WorkOrderDetailWithPermissions

function listed(unread: number): ChatChannel {
  return {
    work_order_id: 4,
    code: 'COM-0004',
    title: 'Impianto',
    registry_name: 'Acme S.p.A.',
    unread_count: unread,
    can_write: true,
    channel: 'work-orders.4.chat',
    last_message: null,
  }
}

/** Prints what the dock would render: its windows and the channels opened from outside. */
function DockProbe() {
  const dock = useChatDock()
  return (
    <output aria-label="dock">
      {dock?.windows.map((window) => window.key).join(',')}|{dock?.extraChannels.map((channel) => `${channel.work_order_id}:${String(channel.can_write)}`).join(',')}
    </output>
  )
}

function renderButton() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ChatDockProvider>
        <WorkOrderChatTabButton workOrder={WORK_ORDER} />
        <DockProbe />
      </ChatDockProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
})

describe('WorkOrderChatTabButton', () => {
  it('shows the unread dot of the channel and opens its conversation in the dock', async () => {
    channelsMock.mockResolvedValue([listed(2)])
    renderButton()

    const button = await screen.findByRole('button', { name: '2 Chat' })
    fireEvent.click(button)

    expect(screen.getByRole('status', { name: 'dock' })).toHaveTextContent('channel:4|4:true')
  })

  it('shows no dot without unread messages', async () => {
    channelsMock.mockResolvedValue([listed(0)])
    renderButton()

    await waitFor(() => expect(channelsMock).toHaveBeenCalled())
    expect(screen.getByRole('button', { name: 'Chat' })).toBeInTheDocument()
  })

  it('opens the conversation even when the work order is not among the user channels (super-admin outside the team)', async () => {
    channelsMock.mockResolvedValue([])
    renderButton()
    await waitFor(() => expect(channelsMock).toHaveBeenCalled())

    fireEvent.click(screen.getByRole('button', { name: 'Chat' }))

    expect(screen.getByRole('status', { name: 'dock' })).toHaveTextContent('channel:4|4:true')
  })
})
