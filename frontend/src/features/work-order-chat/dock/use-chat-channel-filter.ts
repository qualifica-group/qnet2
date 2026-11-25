import { useState } from 'react'
import type { ChatChannel } from '@/features/work-order-chat/types'

export type ChatChannelFilter = 'all' | 'unread'

/** Lower-case, accent-free text, so "attivita" finds "Attività". */
function normalize(text: string): string {
  return text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase()
}

/**
 * Local search (title, code, client) and the "Non letti" filter of the
 * conversation list: all channels are already loaded. `assistantName` is
 * matched too, so the pinned assistant follows the same search; it has no
 * unread, so "Non letti" hides it.
 */
export function useChatChannelFilter(channels: ChatChannel[], assistantName: string) {
  const [query, setQuery] = useState('')
  const [filter, setFilter] = useState<ChatChannelFilter>('all')
  const needle = normalize(query.trim())

  const visible = channels.filter(
    (channel) =>
      (filter === 'all' || channel.unread_count > 0) &&
      (needle === '' ||
        normalize([channel.title, channel.code, channel.registry_name].filter(Boolean).join(' ')).includes(needle)),
  )

  const showsAssistant = filter === 'all' && (needle === '' || normalize(assistantName).includes(needle))

  return { query, setQuery, filter, setFilter, visible, showsAssistant }
}
