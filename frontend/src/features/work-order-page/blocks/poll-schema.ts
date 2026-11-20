import { z } from 'zod'
import type { TFunction } from 'i18next'
import type { CreatePollPayload } from '@/features/work-order-page/types'

/** Mirror of the server rules of `POST /work-orders/{id}/page/polls` (spec 0380). */
export const POLL_QUESTION_MAX = 255
export const POLL_OPTION_MAX = 120
export const POLL_OPTIONS_MIN = 2
export const POLL_OPTIONS_MAX = 10

export function buildPollSchema(t: TFunction) {
  return z
    .object({
      question: z
        .string()
        .trim()
        .min(1, t('workOrderPage.pollDialog.questionRequired'))
        .max(POLL_QUESTION_MAX),
      options: z
        .array(z.object({ label: z.string().trim().min(1, t('workOrderPage.pollDialog.optionRequired')).max(POLL_OPTION_MAX) }))
        .min(POLL_OPTIONS_MIN, t('workOrderPage.pollDialog.optionsMin', { count: POLL_OPTIONS_MIN }))
        .max(POLL_OPTIONS_MAX),
    })
    .superRefine((values, ctx) => {
      const seen = new Set<string>()
      values.options.forEach((option, index) => {
        const key = option.label.toLowerCase()
        if (key !== '' && seen.has(key)) {
          ctx.addIssue({ code: 'custom', path: ['options', index, 'label'], message: t('workOrderPage.pollDialog.optionDuplicate') })
        }
        seen.add(key)
      })
    })
}

export type PollFormValues = z.infer<ReturnType<typeof buildPollSchema>>

export const POLL_FORM_DEFAULTS: PollFormValues = { question: '', options: [{ label: '' }, { label: '' }] }

export function toCreatePollPayload(values: PollFormValues): CreatePollPayload {
  return { question: values.question.trim(), options: values.options.map((option) => option.label.trim()) }
}
