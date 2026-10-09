import { z } from 'zod'
import type { TFunction } from 'i18next'

/** Backend limits (spec 0209 data_contract). */
export const NAME_MAX_LENGTH = 120
export const DESCRIPTION_MAX_LENGTH = 2000
export const RATE_LIMIT_MIN = 1
export const RATE_LIMIT_MAX = 1000

/**
 * Form schema of the API client create/edit dialog, mirroring the server
 * rules. `expires_at` is a `datetime-local` string (`null` = never expires);
 * a value equal to `initialExpiresAt` is exempt from the "future" check so an
 * already-expired client can still be edited without touching its expiry.
 */
export function buildApiClientSchema(t: TFunction, initialExpiresAt: string | null = null) {
  return z.object({
    name: z
      .string()
      .trim()
      .min(1, t('apiIntegrations.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('apiIntegrations.form.nameMax')),
    description: z.string().max(DESCRIPTION_MAX_LENGTH, t('apiIntegrations.form.descriptionMax')),
    rate_limit_per_minute: z
      .number()
      .int(t('apiIntegrations.form.rateLimitRange'))
      .min(RATE_LIMIT_MIN, t('apiIntegrations.form.rateLimitRange'))
      .max(RATE_LIMIT_MAX, t('apiIntegrations.form.rateLimitRange'))
      .nullable(),
    expires_at: z
      .string()
      .nullable()
      .refine(
        (value) => value === null || value === initialExpiresAt || new Date(value).getTime() > Date.now(),
        t('apiIntegrations.form.expiresAtFuture'),
      ),
    is_active: z.boolean(),
  })
}

export type ApiClientFormValues = z.infer<ReturnType<typeof buildApiClientSchema>>
