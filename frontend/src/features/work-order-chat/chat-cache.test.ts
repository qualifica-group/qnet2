import { describe, expect, it } from 'vitest'
import { applyChatEvent, canManageMessage, prependOlder } from '@/features/work-order-chat/chat-cache'
import { chatMessage, chatThread, OPEN_WINDOW, OWN_AUTHOR } from '@/features/work-order-chat/chat-fixtures'

const ME = 1
const NOW = new Date('2026-11-04T10:05:00Z').getTime()

describe('applyChatEvent', () => {
  it('appends a created message from someone else and counts it unread', () => {
    const next = applyChatEvent(chatThread(), { action: 'created', message: chatMessage({ id: 12 }) }, ME)

    expect(next.messages.map((m) => m.id)).toEqual([10, 11, 12])
    expect(next.unread_count).toBe(3)
  })

  it('does not count the own created message as unread', () => {
    const next = applyChatEvent(
      chatThread(),
      { action: 'created', message: chatMessage({ id: 12, author: OWN_AUTHOR }) },
      ME,
    )

    expect(next.unread_count).toBe(2)
  })

  it('deduplicates a created message already in the thread', () => {
    const thread = chatThread()
    const next = applyChatEvent(thread, { action: 'created', message: chatMessage({ id: 11 }) }, ME)

    expect(next).toBe(thread)
  })

  it('replaces the message on updated and deleted, ignores ones outside the window', () => {
    const updated = applyChatEvent(chatThread(), { action: 'updated', message: chatMessage({ id: 10, body: 'new' }) }, ME)
    const deleted = applyChatEvent(
      updated,
      { action: 'deleted', message: chatMessage({ id: 11, body: null, is_deleted: true }) },
      ME,
    )
    const outside = applyChatEvent(deleted, { action: 'updated', message: chatMessage({ id: 3 }) }, ME)

    expect(deleted.messages[0].body).toBe('new')
    expect(deleted.messages[1].is_deleted).toBe(true)
    expect(outside).toBe(deleted)
  })
})

describe('prependOlder', () => {
  it('puts the older page first without duplicating ids and takes its has_more', () => {
    const older = chatThread({ messages: [chatMessage({ id: 8 }), chatMessage({ id: 10 })], has_more: true })
    const next = prependOlder(chatThread(), older)

    expect(next.messages.map((m) => m.id)).toEqual([8, 10, 11])
    expect(next.has_more).toBe(true)
  })
})

describe('canManageMessage', () => {
  const base = { canWrite: true, canModerate: false, currentUserId: ME, now: NOW }

  it('allows the author inside the window only', () => {
    const own = chatMessage({ id: 1, author: OWN_AUTHOR, editable_until: OPEN_WINDOW })

    expect(canManageMessage(own, base)).toBe(true)
    expect(canManageMessage({ ...own, editable_until: '2026-11-04T10:00:00Z' }, base)).toBe(false)
  })

  it('denies others messages, deleted messages and read-only threads', () => {
    const own = chatMessage({ id: 1, author: OWN_AUTHOR, editable_until: OPEN_WINDOW })

    expect(canManageMessage(chatMessage({ id: 2, editable_until: OPEN_WINDOW }), base)).toBe(false)
    expect(canManageMessage({ ...own, is_deleted: true }, base)).toBe(false)
    expect(canManageMessage(own, { ...base, canWrite: false })).toBe(false)
  })

  it('lets a moderator act on any non-deleted message regardless of the window', () => {
    const others = chatMessage({ id: 2 })

    expect(canManageMessage(others, { ...base, canModerate: true })).toBe(true)
    expect(canManageMessage({ ...others, is_deleted: true }, { ...base, canModerate: true })).toBe(false)
    expect(canManageMessage(others, { ...base, canModerate: true, canWrite: false })).toBe(false)
  })
})
