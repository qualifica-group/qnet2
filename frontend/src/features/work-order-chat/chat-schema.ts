import { z } from 'zod'
import type { TFunction } from 'i18next'

/** Backend ceiling of a chat message (spec 0352). */
export const CHAT_MESSAGE_MAX_LENGTH = 2000

/** Mirrors the server rule: trimmed, required, 1..2000 characters. */
export function createChatMessageSchema(t: TFunction) {
  return z.object({
    body: z
      .string()
      .trim()
      .min(1, t('workOrders.chat.validation.required'))
      .max(CHAT_MESSAGE_MAX_LENGTH, t('workOrders.chat.validation.tooLong', { max: CHAT_MESSAGE_MAX_LENGTH })),
  })
}

export type ChatMessageFormValues = z.infer<ReturnType<typeof createChatMessageSchema>>
