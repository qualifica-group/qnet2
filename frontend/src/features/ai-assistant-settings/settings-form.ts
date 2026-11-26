import { z } from 'zod'
import type { TFunction } from 'i18next'
import type {
  AiAssistantSettings,
  UpdateAiAssistantSettingsPayload,
} from '@/features/ai-assistant-settings/types'

/** Same rule as the backend `decimal:0,2`, `min:0`: empty means "no cap". */
const MONEY_PATTERN = /^\d{1,10}(\.\d{1,2})?$/

export const ROUTING_SORTS = ['throughput', 'price', 'latency'] as const
export const DATA_COLLECTIONS = ['deny', 'allow'] as const

export function buildSettingsSchema(t: TFunction, instructionsMaxChars: number) {
  const cap = z.string().refine((value) => value === '' || MONEY_PATTERN.test(value), t('validation.cap'))

  return z.object({
    enabled: z.boolean(),
    model_id: z.string().min(1, t('validation.modelRequired')),
    instructions: z.string().max(instructionsMaxChars, t('validation.instructionsMax', { max: instructionsMaxChars })),
    spend_cap_person_usd: cap,
    spend_cap_total_usd: cap,
    routing: z.object({
      zdr: z.boolean(),
      data_collection: z.enum(DATA_COLLECTIONS),
      require_parameters: z.boolean(),
      sort: z.enum(ROUTING_SORTS),
    }),
  })
}

export type SettingsFormValues = z.infer<ReturnType<typeof buildSettingsSchema>>

export function toFormValues(settings: AiAssistantSettings): SettingsFormValues {
  return {
    enabled: settings.enabled,
    model_id: settings.model_id,
    instructions: settings.instructions,
    spend_cap_person_usd: settings.spend_cap_person_usd ?? '',
    spend_cap_total_usd: settings.spend_cap_total_usd ?? '',
    routing: settings.routing,
  }
}

export function toPayload(values: SettingsFormValues): UpdateAiAssistantSettingsPayload {
  return {
    ...values,
    spend_cap_person_usd: values.spend_cap_person_usd === '' ? null : values.spend_cap_person_usd,
    spend_cap_total_usd: values.spend_cap_total_usd === '' ? null : values.spend_cap_total_usd,
  }
}
