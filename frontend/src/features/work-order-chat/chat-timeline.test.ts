import { describe, expect, it } from 'vitest'
import { chatMessage } from '@/features/work-order-chat/chat-fixtures'
import { buildChatTimeline, formatChatDay, formatChatListTime } from '@/features/work-order-chat/chat-timeline'

const LABELS = { today: 'Today', yesterday: 'Yesterday' }
const ANNA = { id: 2, name: 'Anna Rossi', avatar_url: null }
const BO = { id: 3, name: 'Bo Bianchi', avatar_url: null }

/** A local wall-clock instant, so the day boundaries do not depend on the machine's time zone. */
function local(year: number, month: number, day: number, hour: number, minute = 0): string {
  return new Date(year, month - 1, day, hour, minute).toISOString()
}

describe('buildChatTimeline', () => {
  it('puts a day separator before the first message of each day', () => {
    const items = buildChatTimeline([
      chatMessage({ id: 1, created_at: local(2026, 11, 24, 9) }),
      chatMessage({ id: 2, created_at: local(2026, 11, 24, 18) }),
      chatMessage({ id: 3, created_at: local(2026, 11, 25, 8) }),
    ])

    expect(items.map((item) => (item.kind === 'day' ? 'day' : item.message.id))).toEqual(['day', 1, 2, 'day', 3])
  })

  it('groups the same author within five minutes and starts a new group otherwise', () => {
    const items = buildChatTimeline([
      chatMessage({ id: 1, author: ANNA, created_at: local(2026, 11, 25, 9, 0) }),
      chatMessage({ id: 2, author: ANNA, created_at: local(2026, 11, 25, 9, 4) }),
      chatMessage({ id: 3, author: BO, created_at: local(2026, 11, 25, 9, 5) }),
      chatMessage({ id: 4, author: BO, created_at: local(2026, 11, 25, 9, 20) }),
    ]).filter((item) => item.kind === 'message')

    expect(items.map((item) => item.startsGroup)).toEqual([true, false, true, true])
  })
})

describe('chat dates', () => {
  const now = new Date(2026, 10, 25, 12).getTime()

  it('labels the separators today, yesterday or the full date', () => {
    expect(formatChatDay(local(2026, 11, 25, 8), now, 'en', LABELS)).toBe('Today')
    expect(formatChatDay(local(2026, 11, 24, 23), now, 'en', LABELS)).toBe('Yesterday')
    expect(formatChatDay(local(2026, 11, 20, 8), now, 'en', LABELS)).toContain('2026')
  })

  it('shows the hour today, yesterday, the weekday this week and the date before', () => {
    expect(formatChatListTime(local(2026, 11, 25, 9, 5), now, 'en', LABELS)).toMatch(/09:05/)
    expect(formatChatListTime(local(2026, 11, 24, 9), now, 'en', LABELS)).toBe('Yesterday')
    expect(formatChatListTime(local(2026, 11, 21, 9), now, 'en', LABELS)).toBe('Sat')
    expect(formatChatListTime(local(2026, 11, 1, 9), now, 'en', LABELS)).toBe('11/01/26')
  })
})
