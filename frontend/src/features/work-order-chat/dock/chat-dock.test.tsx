import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import i18n from '@/i18n'
import { ChatDock } from '@/features/work-order-chat/dock/chat-dock'
import { ChatDockProvider } from '@/features/work-order-chat/dock/chat-dock-provider'
import { MAX_OPEN_WINDOWS } from '@/features/work-order-chat/dock/use-chat-dock-windows'
import type { ChatChannel } from '@/features/work-order-chat/types'

/**
 * The always-present chat dock (spec 0432 AC-007): the bar with the total
 * unread, the channel list, a window per opened channel (panel mounted only
 * while expanded), at most MAX_OPEN_WINDOWS, and the realtime that rereads
 * the list.
 */

const channelsMock = vi.fn<() => Promise<ChatChannel[]>>()
vi.mock('@/features/work-order-chat/api', () => ({
  fetchChatChannels: () => channelsMock(),
}))

vi.mock('@/features/work-order-chat/work-order-chat-panel', () => ({
  WorkOrderChatPanel: ({ workOrderId, canWriteAction }: { workOrderId: number; canWriteAction: boolean }) => (
    <div>
      chat-panel {workOrderId} write:{String(canWriteAction)}
    </div>
  ),
}))

let chatEnabled = true
vi.mock('@/features/work-order-chat/use-chat-enabled', () => ({ useChatEnabled: () => chatEnabled }))

let aiAvailable = false
vi.mock('@/features/ai-assistant/hooks/use-ai-dock-entry', () => ({ useAiDockEntry: () => aiAvailable }))
vi.mock('@/features/ai-assistant/components/ai-dock-window', () => ({
  default: ({ minimized }: { minimized: boolean }) => <div>ai-window minimized:{String(minimized)}</div>,
}))

let isMobile = false
vi.mock('@/hooks/use-mobile', () => ({ useIsMobile: () => isMobile }))
vi.mock('@/features/auth/use-auth', () => ({ useAuth: () => ({ user: { id: 1 } }) }))

const listeners = new Map<string, () => void>()
const leaveMock = vi.fn()
vi.mock('@/lib/echo', () => ({
  getEcho: () => ({
    private: (channel: string) => {
      const handle = {
        listen: (_event: string, callback: () => void) => {
          listeners.set(channel, callback)
          return handle
        },
      }
      return handle
    },
    leave: (channel: string) => leaveMock(channel),
  }),
  onEchoReconnect: () => vi.fn(),
}))

function channel(id: number, overrides: Partial<ChatChannel> = {}): ChatChannel {
  return {
    work_order_id: id,
    code: `COM-000${id}`,
    title: `Project ${id}`,
    registry_name: `Client ${id}`,
    unread_count: 0,
    can_write: true,
    channel: `work-orders.${id}.chat`,
    last_message: null,
    ...overrides,
  }
}

function renderDock() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ChatDockProvider>
          <ChatDock />
        </ChatDockProvider>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

const bar = () => screen.getByRole('button', { name: /^Chat/ })

/** The bar appears once the channels are known (no channel and no AI: no dock). */
const findBar = () => screen.findByRole('button', { name: /^Chat/ })

async function openPanel() {
  fireEvent.click(await findBar())
  return screen.findByRole('list', { name: 'Chat channels' })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  listeners.clear()
  isMobile = false
  aiAvailable = false
  chatEnabled = true
})

describe('ChatDock', () => {
  // REQUIREMENT CHANGED (user directive 2026-11-25): no longer always present.
  it('disappears without any channel and without the AI assistant', async () => {
    channelsMock.mockResolvedValue([])
    renderDock()

    await waitFor(() => expect(channelsMock).toHaveBeenCalled())
    expect(screen.queryByRole('region', { name: 'Project chat' })).not.toBeInTheDocument()
  })

  it('with the chat switched off asks no channel and keeps only the AI assistant', async () => {
    chatEnabled = false
    aiAvailable = true
    channelsMock.mockResolvedValue([channel(1)])
    renderDock()

    fireEvent.click(bar())

    expect(await screen.findByRole('button', { name: /AI assistant/ })).toBeInTheDocument()
    expect(screen.queryByRole('searchbox', { name: 'Search a channel' })).not.toBeInTheDocument()
    expect(channelsMock).not.toHaveBeenCalled()
  })

  it('with the chat switched off and no AI assistant renders nothing', () => {
    chatEnabled = false
    renderDock()

    expect(screen.queryByRole('region', { name: 'Project chat' })).not.toBeInTheDocument()
    expect(channelsMock).not.toHaveBeenCalled()
  })

  it('shows the total unread on the bar and lists the channels with client, preview and unread', async () => {
    channelsMock.mockResolvedValue([
      channel(1, {
        unread_count: 2,
        last_message: { author_name: 'Anna Rossi', body_preview: 'ciao', is_deleted: false, created_at: '2026-11-25T10:00:00Z' },
      }),
      channel(2, { unread_count: 1, last_message: { author_name: 'Bo', body_preview: null, is_deleted: true, created_at: '2026-11-24T10:00:00Z' } }),
      channel(3),
    ])
    renderDock()

    expect(await within(await findBar()).findByLabelText('3 unread messages')).toBeInTheDocument()
    const list = await openPanel()
    const rows = within(list).getAllByRole('button')

    expect(rows).toHaveLength(3)
    expect(rows[0]).toHaveTextContent('Project 1')
    expect(rows[0]).toHaveTextContent('Client 1')
    expect(rows[0]).toHaveTextContent('Anna Rossi: ciao')
    expect(within(rows[0]).getByLabelText('2 unread messages')).toBeInTheDocument()
    expect(rows[1]).toHaveTextContent('Message deleted')
    expect(rows[2]).toHaveTextContent('No messages')
  })

  it('opens the channel conversation in a window, minimizes it (panel unmounted) and closes it', async () => {
    channelsMock.mockResolvedValue([channel(1, { can_write: false })])
    renderDock()
    const list = await openPanel()

    fireEvent.click(within(list).getByRole('button', { name: /Project 1/ }))

    expect(screen.getByText('chat-panel 1 write:false')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Open the work order' })).toHaveAttribute('href', '/project-work-orders/1')

    const header = screen.getByRole('button', { name: /^Project 1/, expanded: true })
    fireEvent.click(header)
    expect(header).toHaveAttribute('aria-expanded', 'false')
    expect(screen.queryByText(/chat-panel 1/)).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Open' }))
    expect(screen.getByText('chat-panel 1 write:false')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Close the chat' }))
    expect(screen.queryByRole('button', { name: /^Project 1/, expanded: true })).not.toBeInTheDocument()
    expect(screen.queryByText(/chat-panel 1/)).not.toBeInTheDocument()
  })

  it(`keeps at most ${MAX_OPEN_WINDOWS} windows, closing the oldest`, async () => {
    channelsMock.mockResolvedValue([channel(1), channel(2), channel(3), channel(4)])
    renderDock()
    const list = await openPanel()

    for (const id of [1, 2, 3, 4]) {
      fireEvent.click(within(list).getByRole('button', { name: new RegExp(`Project ${id}`) }))
    }

    expect(screen.queryByText(/chat-panel 1 /)).not.toBeInTheDocument()
    for (const id of [2, 3, 4]) {
      expect(screen.getByText(`chat-panel ${id} write:true`)).toBeInTheDocument()
    }
  })

  it('searches the channels by title, code or client and filters the unread ones', async () => {
    channelsMock.mockResolvedValue([channel(1, { unread_count: 1, registry_name: 'Attività Srl' }), channel(2)])
    renderDock()
    const list = await openPanel()

    fireEvent.change(screen.getByRole('searchbox', { name: 'Search a channel' }), { target: { value: 'attivita' } })
    expect(within(list).getAllByRole('button')).toHaveLength(1)
    expect(within(list).getByRole('button')).toHaveTextContent('Project 1')

    fireEvent.change(screen.getByRole('searchbox', { name: 'Search a channel' }), { target: { value: '' } })
    fireEvent.click(screen.getByRole('radio', { name: 'Unread' }))
    expect(within(screen.getByRole('list', { name: 'Chat channels' })).getAllByRole('button')).toHaveLength(1)

    fireEvent.change(screen.getByRole('searchbox', { name: 'Search a channel' }), { target: { value: 'nothing' } })
    expect(screen.getByText('No channel found.')).toBeInTheDocument()
  })

  it('on a phone keeps one window and closes the list when a channel opens', async () => {
    isMobile = true
    channelsMock.mockResolvedValue([channel(1), channel(2)])
    renderDock()

    fireEvent.click(within(await openPanel()).getByRole('button', { name: /Project 1/ }))
    expect(screen.queryByRole('list', { name: 'Chat channels' })).not.toBeInTheDocument()

    fireEvent.click(within(await openPanel()).getByRole('button', { name: /Project 2/ }))
    expect(screen.queryByText(/chat-panel 1/)).not.toBeInTheDocument()
    expect(screen.getByText('chat-panel 2 write:true')).toBeInTheDocument()
  })

  it('listens to every channel, rereads the list on an event and leaves on unmount', async () => {
    channelsMock.mockResolvedValue([channel(1), channel(2)])
    const { unmount } = renderDock()
    await waitFor(() => expect(listeners.size).toBe(2))
    expect(channelsMock).toHaveBeenCalledTimes(1)

    act(() => listeners.get('work-orders.2.chat')?.())

    await waitFor(() => expect(channelsMock).toHaveBeenCalledTimes(2))
    unmount()
    expect(leaveMock).toHaveBeenCalledWith('work-orders.1.chat')
    expect(leaveMock).toHaveBeenCalledWith('work-orders.2.chat')
  })

  it('gives the panel and every window the same header (2026-11-25)', async () => {
    channelsMock.mockResolvedValue([channel(1)])
    renderDock()
    fireEvent.click(within(await openPanel()).getByRole('button', { name: /Project 1/ }))

    const panelHeader = bar().parentElement
    const windowHeader = screen.getByRole('button', { name: /^Project 1/, expanded: true }).parentElement

    expect(panelHeader?.className).toBe(windowHeader?.className)
    expect(panelHeader).toHaveClass('h-8')
  })
})

describe('ChatDock — the AI assistant as a conversation (spec 0432)', () => {
  it('pins the assistant on top of the channels and opens it in a window', async () => {
    aiAvailable = true
    channelsMock.mockResolvedValue([channel(1)])
    renderDock()
    await openPanel()

    const rows = within(screen.getByRole('region', { name: 'Project chat' })).getAllByRole('button', { name: /AI assistant|Project 1/ })
    expect(rows[0]).toHaveTextContent('AI assistant')

    fireEvent.click(rows[0])

    expect(await screen.findByText('ai-window minimized:false')).toBeInTheDocument()
  })

  it('lists the assistant even without channels, and hides it under the unread filter', async () => {
    aiAvailable = true
    channelsMock.mockResolvedValue([])
    renderDock()
    fireEvent.click(bar())

    expect(await screen.findByRole('button', { name: /AI assistant/ })).toBeInTheDocument()
    expect(await screen.findByText('You are not in the team of any project work order.')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('radio', { name: 'Unread' }))
    expect(screen.queryByRole('button', { name: /AI assistant/ })).not.toBeInTheDocument()
  })

  it('has no assistant entry when it is not available to the user', async () => {
    channelsMock.mockResolvedValue([channel(1)])
    renderDock()
    await openPanel()

    expect(screen.queryByRole('button', { name: /AI assistant/ })).not.toBeInTheDocument()
  })
})
