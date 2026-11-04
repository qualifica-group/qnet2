import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import i18n from '@/i18n'
import { chatMessage, chatThread, OPEN_WINDOW, OWN_AUTHOR } from '@/features/work-order-chat/chat-fixtures'
import { CHAT_MESSAGE_MAX_LENGTH } from '@/features/work-order-chat/chat-schema'
import type { ChatEventPayload, ChatThread } from '@/features/work-order-chat/types'
import { WorkOrderChatPanel } from '@/features/work-order-chat/work-order-chat-panel'
import { WorkOrderChatTabLabel } from '@/features/work-order-chat/work-order-chat-tab-label'

const fetchMock = vi.fn<(id: number, params?: unknown) => Promise<ChatThread>>()
const sendMock = vi.fn()
const readMock = vi.fn()
const deleteMock = vi.fn()
vi.mock('@/features/work-order-chat/api', () => ({
  fetchChatMessages: (id: number, params?: unknown) => fetchMock(id, params),
  sendChatMessage: (...args: unknown[]) => sendMock(...args),
  editChatMessage: vi.fn(),
  deleteChatMessage: (...args: unknown[]) => deleteMock(...args),
  markChatRead: (...args: unknown[]) => readMock(...args),
}))

vi.mock('@/features/auth/use-auth', () => ({ useAuth: () => ({ user: { id: 1 } }) }))

let listener: ((event: ChatEventPayload) => void) | null = null
let reconnect: (() => void) | null = null
const privateMock = vi.fn()
const leaveMock = vi.fn()
vi.mock('@/lib/echo', () => ({
  getEcho: () => ({
    private: (channel: string) => {
      privateMock(channel)
      const handle = {
        listen: (_event: string, callback: (event: ChatEventPayload) => void) => {
          listener = callback
          return handle
        },
      }
      return handle
    },
    leave: (channel: string) => leaveMock(channel),
  }),
  onEchoReconnect: (callback: () => void) => {
    reconnect = callback
    return vi.fn()
  },
}))

function renderChat(writeChat = true) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <WorkOrderChatTabLabel workOrderId={4} />
        <WorkOrderChatPanel workOrderId={4} canWriteAction={writeChat} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  listener = null
  reconnect = null
  fetchMock.mockResolvedValue(chatThread())
  readMock.mockResolvedValue({ last_read_message_id: 11, unread_count: 0 })
})

describe('realtime (AC-018, AC-019)', () => {
  it('subscribes with the channel received from the backend and leaves on unmount', async () => {
    const { unmount } = renderChat()
    await screen.findByText('message 11')

    expect(privateMock).toHaveBeenCalledWith('work-orders.4.chat')
    unmount()

    expect(leaveMock).toHaveBeenCalledWith('work-orders.4.chat')
  })

  it('applies created, updated and deleted events without a reload, deduplicating repeats', async () => {
    renderChat()
    await screen.findByText('message 11')

    act(() => listener?.({ action: 'created', message: chatMessage({ id: 12, body: 'hello team' }) }))
    expect(await screen.findByText('hello team')).toBeInTheDocument()

    act(() => listener?.({ action: 'created', message: chatMessage({ id: 12, body: 'hello team' }) }))
    expect(screen.getAllByText('hello team')).toHaveLength(1)

    act(() =>
      listener?.({
        action: 'updated',
        message: chatMessage({ id: 12, body: 'hello all', edited_at: '2026-11-04T10:01:00Z' }),
      }),
    )
    expect(await screen.findByText('hello all')).toBeInTheDocument()
    expect(screen.getByText('(edited)')).toBeInTheDocument()

    act(() => listener?.({ action: 'deleted', message: chatMessage({ id: 12, body: null, is_deleted: true }) }))
    expect(await screen.findByText('Message deleted')).toBeInTheDocument()
    expect(screen.queryByText('hello all')).not.toBeInTheDocument()
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('does not duplicate the message the user just sent when its own event arrives', async () => {
    sendMock.mockResolvedValue(chatMessage({ id: 12, author: OWN_AUTHOR, body: 'mine' }))
    renderChat()
    await screen.findByText('message 11')

    fireEvent.change(screen.getByRole('textbox', { name: 'Write a message' }), { target: { value: 'mine' } })
    await waitFor(() => expect(screen.getByRole('button', { name: 'Send' })).toBeEnabled())
    fireEvent.click(screen.getByRole('button', { name: 'Send' }))
    await screen.findByText('mine')

    act(() => listener?.({ action: 'created', message: chatMessage({ id: 12, author: OWN_AUTHOR, body: 'mine' }) }))

    expect(screen.getAllByText('mine')).toHaveLength(1)
    expect(sendMock).toHaveBeenCalledWith(4, 'mine')
  })

  it('reloads the thread when the connection comes back', async () => {
    renderChat()
    await screen.findByText('message 11')
    expect(fetchMock).toHaveBeenCalledTimes(1)

    act(() => reconnect?.())

    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2))
  })
})

describe('read-only and actions (AC-020)', () => {
  it('hides the composer and shows the notice with write_chat false', async () => {
    renderChat(false)
    await screen.findByText('message 11')

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(screen.getByText('Read-only chat: you cannot send messages.')).toBeInTheDocument()
  })

  it('is read-only when the thread itself cannot be written (closed work order)', async () => {
    fetchMock.mockResolvedValue(chatThread({ can_write: false }))
    renderChat()

    expect(await screen.findByText('Read-only chat: you cannot send messages.')).toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
  })

  it('shows edit and delete only on the own message inside its window', async () => {
    fetchMock.mockResolvedValue(
      chatThread({
        messages: [
          chatMessage({ id: 10 }),
          chatMessage({ id: 11, author: OWN_AUTHOR, editable_until: OPEN_WINDOW }),
          chatMessage({ id: 12, author: OWN_AUTHOR }),
        ],
      }),
    )
    renderChat()
    await screen.findByText('message 12')

    expect(screen.getAllByRole('button', { name: 'Edit' })).toHaveLength(1)
    expect(screen.getAllByRole('button', { name: 'Delete' })).toHaveLength(1)
  })

  it('asks for confirmation before deleting and deletes only on confirm', async () => {
    fetchMock.mockResolvedValue(
      chatThread({ messages: [chatMessage({ id: 11, author: OWN_AUTHOR, editable_until: OPEN_WINDOW })] }),
    )
    deleteMock.mockResolvedValue(chatMessage({ id: 11, author: OWN_AUTHOR, body: null, is_deleted: true }))
    renderChat()
    await screen.findByText('message 11')

    fireEvent.click(screen.getByRole('button', { name: 'Delete' }))
    let dialog = await screen.findByRole('alertdialog', { name: 'Delete this message?' })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }))
    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(deleteMock).not.toHaveBeenCalled()

    fireEvent.click(screen.getByRole('button', { name: 'Delete' }))
    dialog = await screen.findByRole('alertdialog', { name: 'Delete this message?' })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Delete' }))

    await waitFor(() => expect(deleteMock).toHaveBeenCalledTimes(1))
    expect(deleteMock.mock.calls[0]).toContain(11)
  })

  it('shows the actions on every non-deleted message for a moderator', async () => {
    fetchMock.mockResolvedValue(
      chatThread({
        can_moderate: true,
        messages: [
          chatMessage({ id: 10 }),
          chatMessage({ id: 11 }),
          chatMessage({ id: 12, body: null, is_deleted: true }),
        ],
      }),
    )
    renderChat()
    await screen.findByText('message 11')

    expect(screen.getAllByRole('button', { name: 'Edit' })).toHaveLength(2)
  })
})

describe('composer (AC-021)', () => {
  it('disables send when empty and announces the error through the aria triad', async () => {
    renderChat()
    await screen.findByText('message 11')
    const input = screen.getByRole('textbox', { name: 'Write a message' })

    expect(screen.getByRole('button', { name: 'Send' })).toBeDisabled()

    fireEvent.change(input, { target: { value: 'x' } })
    fireEvent.change(input, { target: { value: '' } })

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('Write a message.')
    expect(input).toHaveAttribute('aria-invalid', 'true')
    expect(input).toHaveAttribute('aria-describedby', alert.id)
    expect(screen.getByRole('button', { name: 'Send' })).toBeDisabled()
  })

  it('disables send above the limit with an announced error', async () => {
    renderChat()
    await screen.findByText('message 11')
    const input = screen.getByRole('textbox', { name: 'Write a message' })

    fireEvent.change(input, { target: { value: 'a'.repeat(CHAT_MESSAGE_MAX_LENGTH + 1) } })

    expect(await screen.findByRole('alert')).toHaveTextContent('2000')
    expect(input).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByRole('button', { name: 'Send' })).toBeDisabled()
  })
})

describe('mark as read (AC-022)', () => {
  it('posts the last id once loaded and clears the badge', async () => {
    renderChat()

    expect(await screen.findByLabelText('2 unread messages')).toBeInTheDocument()
    await waitFor(() => expect(readMock).toHaveBeenCalledWith(4, 11))
    await waitFor(() => expect(screen.queryByLabelText('2 unread messages')).not.toBeInTheDocument())
  })

  it('posts again with the new last id when a message arrives', async () => {
    renderChat()
    await waitFor(() => expect(readMock).toHaveBeenCalledWith(4, 11))
    readMock.mockResolvedValue({ last_read_message_id: 12, unread_count: 0 })

    act(() => listener?.({ action: 'created', message: chatMessage({ id: 12 }) }))

    await waitFor(() => expect(readMock).toHaveBeenCalledWith(4, 12))
  })
})
