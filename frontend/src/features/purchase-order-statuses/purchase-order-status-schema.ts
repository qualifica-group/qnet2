import { z } from 'zod'
import type { TFunction } from 'i18next'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { isKnownIconName } from '@/features/custom-fields/icon-catalog'

/** Backend `name` column limit (`max:191`, unique). */
const NAME_MAX_LENGTH = 191

/**
 * Zod schema of the in-place edit of an ODA status (spec 0248 `data_contract`):
 * only name, color and icon exist. `color` is a palette TOKEN, `icon` a curated
 * lucide name with the empty string meaning "unset" (mapped to `null` by the
 * payload builder); both are checked against the allow-lists the backend uses.
 */
export function buildPurchaseOrderStatusSchema(t: TFunction) {
  return z.object({
    name: z
      .string()
      .min(1, t('purchaseOrderStatuses.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('purchaseOrderStatuses.form.nameMax')),
    color: z
      .string()
      .min(1, t('purchaseOrderStatuses.form.colorRequired'))
      .refine((token) => swatchClassFor(token) !== undefined, t('purchaseOrderStatuses.form.colorInvalid')),
    icon: z
      .string()
      .refine((name) => name === '' || isKnownIconName(name), t('purchaseOrderStatuses.form.iconInvalid')),
  })
}

export type PurchaseOrderStatusFormValues = z.infer<ReturnType<typeof buildPurchaseOrderStatusSchema>>
