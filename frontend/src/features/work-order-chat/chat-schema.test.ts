import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import { CHAT_MESSAGE_MAX_LENGTH, createChatMessageSchema } from '@/features/work-order-chat/chat-schema'

const t = ((key: string) => key) as unknown as TFunction
const schema = createChatMessageSchema(t)

describe('createChatMessageSchema', () => {
  it('trims the body', () => {
    expect(schema.parse({ body: '  hi  ' })).toEqual({ body: 'hi' })
  })

  it('rejects an empty or whitespace-only body', () => {
    expect(schema.safeParse({ body: '' }).success).toBe(false)
    expect(schema.safeParse({ body: '   ' }).success).toBe(false)
  })

  it('accepts exactly the limit and rejects one more', () => {
    expect(schema.safeParse({ body: 'a'.repeat(CHAT_MESSAGE_MAX_LENGTH) }).success).toBe(true)
    expect(schema.safeParse({ body: 'a'.repeat(CHAT_MESSAGE_MAX_LENGTH + 1) }).success).toBe(false)
  })
})
