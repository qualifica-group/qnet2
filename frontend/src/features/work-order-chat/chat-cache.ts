import type { ChatEventPayload, ChatMessage, ChatReadResult, ChatThread } from '@/features/work-order-chat/types'

function byId(a: ChatMessage, b: ChatMessage): number {
  return a.id - b.id
}

/**
 * Applies a broadcast (or a mutation result) to the cached thread. `created` is
 * deduplicated by id (the author receives their own event too); `updated` and
 * `deleted` replace the message only if it is in the loaded window.
 */
export function applyChatEvent(thread: ChatThread, event: ChatEventPayload, currentUserId: number | null): ChatThread {
  const { action, message } = event
  const exists = thread.messages.some((item) => item.id === message.id)

  if (action === 'created') {
    if (exists) {
      return thread
    }
    const isOthers = message.author?.id !== currentUserId
    return {
      ...thread,
      messages: [...thread.messages, message].sort(byId),
      unread_count: isOthers && !message.is_deleted ? thread.unread_count + 1 : thread.unread_count,
    }
  }

  if (!exists) {
    return thread
  }
  return { ...thread, messages: thread.messages.map((item) => (item.id === message.id ? message : item)) }
}

/** Prepends an older page, skipping ids already present. */
export function prependOlder(thread: ChatThread, older: ChatThread): ChatThread {
  const known = new Set(thread.messages.map((item) => item.id))
  const fresh = older.messages.filter((item) => !known.has(item.id))
  return { ...thread, messages: [...fresh, ...thread.messages].sort(byId), has_more: older.has_more }
}

export function applyReadResult(thread: ChatThread, result: ChatReadResult): ChatThread {
  return { ...thread, last_read_message_id: result.last_read_message_id, unread_count: result.unread_count }
}

/** Spec 0359 data_contract: the client-side mirror of the server's edit/delete rule. */
export function canManageMessage(
  message: ChatMessage,
  options: { canWrite: boolean; canModerate: boolean; currentUserId: number | null; now: number },
): boolean {
  if (!options.canWrite || message.is_deleted) {
    return false
  }
  if (options.canModerate) {
    return true
  }
  return (
    options.currentUserId !== null &&
    message.author?.id === options.currentUserId &&
    options.now < new Date(message.editable_until).getTime()
  )
}
