/** Contract of the project work order chat (spec 0359 data_contract). */

export interface ChatAuthor {
  id: number
  name: string
  avatar_url: string | null
}

/** Same shape in the REST responses and in the broadcast payload. */
export interface ChatMessage {
  id: number
  work_order_id: number
  /** Null when the user was removed. */
  author: ChatAuthor | null
  /** Null when `is_deleted` is true. */
  body: string | null
  is_deleted: boolean
  edited_at: string | null
  created_at: string
  editable_until: string
}

/** `data` of `GET /work-orders/{id}/chat/messages`: messages in ascending id order. */
export interface ChatThread {
  messages: ChatMessage[]
  has_more: boolean
  can_write: boolean
  can_moderate: boolean
  unread_count: number
  last_read_message_id: number | null
  /** Channel name without the `private-` prefix: used as received, never rebuilt. */
  channel: string
}

export interface ChatListParams {
  before_id?: number
  limit?: number
}

export type ChatEventAction = 'created' | 'updated' | 'deleted'

export interface ChatEventPayload {
  action: ChatEventAction
  message: ChatMessage
}

export interface ChatReadResult {
  last_read_message_id: number
  unread_count: number
}
