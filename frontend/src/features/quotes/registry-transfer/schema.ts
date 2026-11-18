import { z } from 'zod'
import type { TFunction } from 'i18next'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { QuoteRegistryTransferPayload } from '@/features/quotes/registry-transfer/api'

/** Same cap as `quotes.title` on the server. */
const TITLE_MAX_LENGTH = 191

/** The dialog's values: the destination anagrafica, the offer's title (B-D8) and its "Informazioni aggiuntive". */
export interface QuoteRegistryTransferFormValues {
  registry_id: number | null
  title: string
  attribute_values: Record<string, CustomFieldValue>
}

/**
 * The destination is required and must differ from the current anagrafica;
 * the "Informazioni aggiuntive" are validated per code by the server, like the
 * offer update (the dialog shows its 422 on the field).
 */
export function buildQuoteRegistryTransferSchema(t: TFunction, currentRegistryId: number | null) {
  return z.object({
    registry_id: z
      .number({ error: t('quotes.registryTransfer.registryRequired') })
      .nullable()
      .refine((value) => value !== null, t('quotes.registryTransfer.registryRequired'))
      .refine((value) => value !== currentRegistryId, t('quotes.registryTransfer.sameRegistry')),
    title: z.string().max(TITLE_MAX_LENGTH, t('quotes.form.titleMax')),
    attribute_values: z.record(z.string(), z.custom<CustomFieldValue>()),
  })
}

/**
 * The title travels only when edited (B-D8): an untouched automatic title must
 * not turn manual. A cleared field sends `''`, which the server reads as "back
 * to the automatic title".
 */
export function toQuoteRegistryTransferPayload(
  values: QuoteRegistryTransferFormValues,
  currentTitle: string,
): QuoteRegistryTransferPayload {
  const payload: QuoteRegistryTransferPayload = {
    registry_id: values.registry_id as number,
    attribute_values: values.attribute_values,
  }

  return values.title === currentTitle ? payload : { ...payload, title: values.title }
}
