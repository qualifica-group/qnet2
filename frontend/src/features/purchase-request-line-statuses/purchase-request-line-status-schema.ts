import { z } from 'zod'
import type { TFunction } from 'i18next'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { isKnownIconName } from '@/features/custom-fields/icon-catalog'

/** Backend `name` column limit (`max:191`, unique). */
const NAME_MAX_LENGTH = 191

/**
 * Zod schema of the line status edit form (spec 0248 D-2): only name, color
 * and icon are editable. `color` is a palette TOKEN, `icon` a curated lucide
 * name with the empty string meaning "unset". Both are checked against the
 * allow-lists the backend validates with (defense in depth).
 */
export function buildPurchaseRequestLineStatusSchema(t: TFunction) {
  return z.object({
    name: z
      .string()
      .min(1, t('purchaseRequestLineStatuses.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('purchaseRequestLineStatuses.form.nameMax')),
    color: z
      .string()
      .min(1, t('purchaseRequestLineStatuses.form.colorRequired'))
      .refine((token) => swatchClassFor(token) !== undefined, t('purchaseRequestLineStatuses.form.colorInvalid')),
    icon: z
      .string()
      .refine((name) => name === '' || isKnownIconName(name), t('purchaseRequestLineStatuses.form.iconInvalid')),
  })
}

export type PurchaseRequestLineStatusFormValues = z.infer<ReturnType<typeof buildPurchaseRequestLineStatusSchema>>
