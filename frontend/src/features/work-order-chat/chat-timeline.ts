import type { ChatMessage } from '@/features/work-order-chat/types'

/** Messages of the same author closer than this read as one group: name and time shown once. */
export const CHAT_GROUP_WINDOW_MS = 5 * 60_000

const DAY_MS = 24 * 60 * 60_000

/** Days back within which the channel list shows the weekday instead of the date. */
const WEEKDAY_RANGE_DAYS = 6

export type ChatTimelineItem =
  | { kind: 'day'; key: string; date: string }
  | { kind: 'message'; message: ChatMessage; startsGroup: boolean }

export interface ChatDayLabels {
  today: string
  yesterday: string
}

function localDayKey(date: Date): string {
  return `${date.getFullYear()}-${date.getMonth() + 1}-${date.getDate()}`
}

/** Whole local days between the two instants' calendar days (0 = same day). */
function daysBetween(earlier: Date, later: Date): number {
  const start = new Date(earlier.getFullYear(), earlier.getMonth(), earlier.getDate()).getTime()
  const end = new Date(later.getFullYear(), later.getMonth(), later.getDate()).getTime()
  return Math.round((end - start) / DAY_MS)
}

/**
 * The conversation as rendered: a day separator before the first message of
 * each local day, and each message flagged when it starts a group (other
 * author, other day, or more than CHAT_GROUP_WINDOW_MS after the previous).
 */
export function buildChatTimeline(messages: ChatMessage[]): ChatTimelineItem[] {
  const items: ChatTimelineItem[] = []
  let previous: ChatMessage | null = null

  for (const message of messages) {
    const created = new Date(message.created_at)
    const dayKey = localDayKey(created)
    const newDay = previous === null || localDayKey(new Date(previous.created_at)) !== dayKey
    if (newDay) {
      items.push({ kind: 'day', key: dayKey, date: message.created_at })
    }
    const startsGroup =
      newDay ||
      previous === null ||
      (previous.author?.id ?? null) !== (message.author?.id ?? null) ||
      created.getTime() - new Date(previous.created_at).getTime() > CHAT_GROUP_WINDOW_MS
    items.push({ kind: 'message', message, startsGroup })
    previous = message
  }

  return items
}

/** Day separator text: "Oggi", "Ieri", or the full date. */
export function formatChatDay(iso: string, now: number, locale: string, labels: ChatDayLabels): string {
  const days = daysBetween(new Date(iso), new Date(now))
  if (days === 0) return labels.today
  if (days === 1) return labels.yesterday
  return new Date(iso).toLocaleDateString(locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}

/** Time of a message inside the conversation (the day comes from its separator). */
export function formatChatClock(iso: string, locale: string): string {
  return new Date(iso).toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' })
}

/** Channel list time, like messaging apps: the hour today, "Ieri", the weekday this week, else the date. */
export function formatChatListTime(iso: string, now: number, locale: string, labels: ChatDayLabels): string {
  const date = new Date(iso)
  const days = daysBetween(date, new Date(now))
  if (days === 0) return formatChatClock(iso, locale)
  if (days === 1) return labels.yesterday
  if (days <= WEEKDAY_RANGE_DAYS) return date.toLocaleDateString(locale, { weekday: 'short' })
  return date.toLocaleDateString(locale, { day: '2-digit', month: '2-digit', year: '2-digit' })
}
