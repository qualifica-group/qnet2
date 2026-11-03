import type { TFunction } from 'i18next'
import { z } from 'zod'
import { AI_MENTION_MODULE_KEYS, type AiAssistantLimits } from '@/features/ai-assistant/types'

const BYTES_PER_KB = 1024
const BYTES_PER_MB = 1024 * 1024

/**
 * Composer schema built from the limits of `GET /ai-assistant/config`, so the
 * client mirrors the server rules (spec 0347 AC-013/AC-023); the server stays
 * the authority.
 */
export function createComposerSchema(limits: AiAssistantLimits, t: TFunction) {
  const maxFileBytes = limits.max_file_size_kb * BYTES_PER_KB

  return z.object({
    content: z
      .string()
      .trim()
      .min(1, t('aiAssistant.errors.contentRequired'))
      .max(limits.max_message_chars, t('aiAssistant.errors.contentTooLong', { max: limits.max_message_chars })),
    files: z.array(z.instanceof(File)).superRefine((files, ctx) => {
      if (files.length > limits.max_files) {
        ctx.addIssue({ code: 'custom', message: t('aiAssistant.errors.tooManyFiles', { max: limits.max_files }) })
        return
      }
      for (const file of files) {
        if (!limits.accepted_mimes.includes(file.type)) {
          ctx.addIssue({ code: 'custom', message: t('aiAssistant.errors.fileType', { name: file.name }) })
          return
        }
        if (file.size > maxFileBytes) {
          ctx.addIssue({
            code: 'custom',
            message: t('aiAssistant.errors.fileTooLarge', {
              name: file.name,
              max: Math.round((maxFileBytes / BYTES_PER_MB) * 10) / 10,
            }),
          })
          return
        }
      }
    }),
    mentions: z
      .array(
        z.object({
          module: z.enum(AI_MENTION_MODULE_KEYS),
          id: z.number(),
          label: z.string(),
        }),
      )
      .max(limits.max_mentions, t('aiAssistant.errors.tooManyMentions', { max: limits.max_mentions })),
  })
}

export type AiComposerSchema = ReturnType<typeof createComposerSchema>
export type AiComposerValues = z.infer<AiComposerSchema>

export const EMPTY_COMPOSER_VALUES: AiComposerValues = { content: '', files: [], mentions: [] }
