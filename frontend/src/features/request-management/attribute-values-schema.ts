import { z } from 'zod'
import type { TFunction } from 'i18next'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { ApplicableAttribute } from '@/features/request-management/types'

/**
 * The dynamic `attribute_values` shape, one key per applicable attribute
 * `code`. MIRRORS the backend's `AttributeValueValidator` (per-type rule), it
 * does not replace it — the server stays authoritative.
 *
 * Extracted from `request-work-schema.ts` so the create form builds the SAME
 * shape from the same attributes (user directive 2026-07-31): the two forms
 * render one identical dynamic-fields section, and a per-type rule fixed on
 * one channel can no longer stay wrong on the other.
 *
 * `is_required` is deliberately NOT here: on the panel it is gated on the map
 * actually travelling (sparse PATCH), on the create form it always applies —
 * only the caller knows which, so each schema adds it in its own refinement.
 */

/** `enum` is checked against `attribute.options`; every other type gets its native shape. */
function buildAttributeScalarSchema(attribute: ApplicableAttribute, t: TFunction): z.ZodTypeAny {
  switch (attribute.type) {
    case 'integer':
    case 'decimal':
      return z.number().nullable()
    case 'boolean':
      return z.boolean()
    case 'enum': {
      const values = new Set(attribute.options.map((option) => option.value))
      return z
        .string()
        .nullable()
        .superRefine((value, ctx) => {
          if (value !== null && !values.has(value)) {
            ctx.addIssue({
              code: 'custom',
              message: t('requestManagement.workPanel.validation.enumInvalid', {
                defaultValue: 'Select a valid option.',
              }),
            })
          }
        })
    }
    case 'relation':
      return z.union([z.number(), z.array(z.number()), z.null()])
    // text/textarea + the string-backed scalars (date/datetime/time/email/url/color).
    default:
      return z.string().nullable()
  }
}

/**
 * `buildAttributeValuesSchema` derives its shape from a runtime-keyed
 * `Record<string, ZodTypeAny>`, so Zod infers it as `Record<string, unknown>`
 * — re-typed to the real value domain (mirrors `asCustomFieldsField`) so it
 * embeds cleanly under a form's `attribute_values` key.
 */
export type TypedAttributeValuesSchema = z.ZodType<
  Record<string, CustomFieldValue>,
  Record<string, CustomFieldValue>
>

export function buildAttributeValuesSchema(attributes: ApplicableAttribute[], t: TFunction) {
  const shape: Record<string, z.ZodTypeAny> = {}
  for (const attribute of attributes) {
    shape[attribute.code] = buildAttributeScalarSchema(attribute, t)
  }

  return z.object(shape)
}
