import type { ChatMessage, ChatThread } from '@/features/work-order-chat/types'

const PAST = '2000-01-01T00:15:00Z'

/** An `editable_until` that never expires within a test run. */
export const OPEN_WINDOW = '2999-01-01T00:00:00Z'

export const OWN_AUTHOR = { id: 1, name: 'Me Myself', avatar_url: null }

export function chatMessage(overrides: Partial<ChatMessage> & Pick<ChatMessage, 'id'>): ChatMessage {
  return {
    work_order_id: 4,
    author: { id: 2, name: 'Anna Rossi', avatar_url: null },
    body: `message ${overrides.id}`,
    is_deleted: false,
    edited_at: null,
    created_at: '2026-11-04T10:00:00Z',
    editable_until: PAST,
    ...overrides,
  }
}

export function chatThread(overrides: Partial<ChatThread> = {}): ChatThread {
  return {
    messages: [chatMessage({ id: 10 }), chatMessage({ id: 11 })],
    has_more: false,
    can_write: true,
    can_moderate: false,
    unread_count: 2,
    last_read_message_id: null,
    channel: 'work-orders.4.chat',
    ...overrides,
  }
}
