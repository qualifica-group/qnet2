import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  buildAttributeValuesSchema,
  type TypedAttributeValuesSchema,
} from '@/features/request-management/attribute-values-schema'
import { isEmptyCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import type { ApplicableAttributeSummary } from '@/features/work-orders/types'

/** Mirrors `WorkOrder.title`'s `string(191)` column limit (`workOrders.form.titleMax`). */
const TITLE_MAX_LENGTH = 191

/**
 * "Programma" (spec 0095 D-11, extended by spec 0096 D-5, spec 0098 AC-019):
 * the dialog carries exactly the fields a commessa cannot be left without —
 * `title`, `type`, `start_date` and at least one Responsabile — plus at
 * least one selected offer line (AC-061) and the dynamic `attribute_values`
 * resolved from those SAME lines (spec 0098, same builder the work order
 * form uses). The Partecipanti are deliberately absent: they are assigned
 * later from the work order's own form.
 *
 * Messages reuse the work-order form's own strings (`workOrders.form.*`)
 * rather than duplicating them — same fields, same rules, same wording.
 */
export function buildContractProgramSchema(t: TFunction, attributes: ApplicableAttributeSummary[] = []) {
  const requiredCodes = attributes.filter((attribute) => attribute.is_required).map((attribute) => attribute.code)

  return z
    .object({
      title: z
        .string()
        .min(1, t('workOrders.form.titleRequired'))
        .max(TITLE_MAX_LENGTH, t('workOrders.form.titleMax')),
      type: z.enum(['processing', 'project'], { message: t('workOrders.form.typeRequired') }),
      start_date: z.string().min(1, t('workOrders.form.startDateRequired')),
      supervisor_ids: z.array(z.number()).min(1, t('workOrders.form.supervisorsRequired')),
      quote_line_ids: z.array(z.number()).min(1, t('contracts.actions.programDialog.linesRequired')),
      attribute_values: buildAttributeValuesSchema(attributes, t) as unknown as TypedAttributeValuesSchema,
    })
    .superRefine((values, ctx) => {
      for (const code of requiredCodes) {
        if (isEmptyCustomFieldValue(values.attribute_values[code])) {
          ctx.addIssue({
            code: z.ZodIssueCode.custom,
            path: ['attribute_values', code],
            message: t('attributeValues.validation.required', { defaultValue: 'This field is required.' }),
          })
        }
      }
    })
}

export type ContractProgramFormValues = z.infer<ReturnType<typeof buildContractProgramSchema>>

export function contractProgramDefaultValues(): ContractProgramFormValues {
  return {
    title: '',
    type: 'processing',
    start_date: '',
    supervisor_ids: [],
    quote_line_ids: [],
    attribute_values: {},
  }
}
