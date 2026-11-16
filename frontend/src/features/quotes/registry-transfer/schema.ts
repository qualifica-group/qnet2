import { z } from 'zod'
import type { TFunction } from 'i18next'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/** The dialog's values: the destination anagrafica and the offer's "Informazioni aggiuntive". */
export interface QuoteRegistryTransferFormValues {
  registry_id: number | null
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
    attribute_values: z.record(z.string(), z.custom<CustomFieldValue>()),
  })
}
