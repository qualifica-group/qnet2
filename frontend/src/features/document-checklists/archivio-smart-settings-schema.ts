import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Spec 0403: `base_url` url max 255; `token` optional (empty = unchanged), 10..500 when present.
 * Spec 0428: `webhook_secret` follows the same rule as the token.
 */
export const SETTINGS_BASE_URL_MAX_LENGTH = 255
export const SETTINGS_TOKEN_MIN_LENGTH = 10
export const SETTINGS_TOKEN_MAX_LENGTH = 500

export function buildArchivioSmartSettingsSchema(t: TFunction) {
  return z.object({
    base_url: z
      .string()
      .min(1, t('documentChecklists.connection.validation.baseUrlRequired'))
      .max(SETTINGS_BASE_URL_MAX_LENGTH, t('documentChecklists.connection.validation.baseUrlMax'))
      .refine((value) => z.url().safeParse(value).success, t('documentChecklists.connection.validation.baseUrlInvalid')),
    token: z
      .string()
      .max(SETTINGS_TOKEN_MAX_LENGTH, t('documentChecklists.connection.validation.tokenMax'))
      .refine(
        (value) => value.length === 0 || value.length >= SETTINGS_TOKEN_MIN_LENGTH,
        t('documentChecklists.connection.validation.tokenMin'),
      ),
    webhook_secret: z
      .string()
      .max(SETTINGS_TOKEN_MAX_LENGTH, t('documentChecklists.connection.validation.webhookSecretMax'))
      .refine(
        (value) => value.length === 0 || value.length >= SETTINGS_TOKEN_MIN_LENGTH,
        t('documentChecklists.connection.validation.webhookSecretMin'),
      ),
  })
}

export type ArchivioSmartSettingsFormValues = z.infer<ReturnType<typeof buildArchivioSmartSettingsSchema>>
