import { z } from 'zod'
import type { TFunction } from 'i18next'
import { isEmptyCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import type { ProductLineRow } from '@/features/product-lines/product-lines-field'
import { quoteLineRowSchema } from '@/features/quotes/quote-schema'
import {
  buildAttributeValuesSchema,
  type TypedAttributeValuesSchema,
} from '@/features/request-management/attribute-values-schema'
import { attributeValuesFilled } from '@/features/request-management/request-create-payload'
import type { ApplicableAttribute } from '@/features/request-management/types'

/** Backend per-tab row ceiling (`max:200`), mirrored from `quote-schema.ts`. */
const MAX_OFFER_LINES = 200

/**
 * D-3: `product_lines` is mandatory — at least one row — and every row
 * present must be complete (both ids chosen), mirroring the opportunity
 * form's own product-lines rule. The shared `ProductLinesField` enforces the
 * row shape (category disabled until a function is chosen); this only gates
 * the SET.
 */
function buildProductLinesSchema(t: TFunction) {
  return z.array(z.custom<ProductLineRow>()).superRefine((rows, ctx) => {
    if (rows.length === 0) {
      ctx.addIssue({
        code: 'custom',
        message: t('requestManagement.form.create.validation.productLinesRequired'),
      })
      return
    }
    rows.forEach((row, index) => {
      if (row.business_function_id === null || row.product_category_id === null) {
        ctx.addIssue({
          code: 'custom',
          path: [index],
          message: t('requestManagement.form.create.validation.productLineIncomplete'),
        })
      }
    })
    // Spec 0077 INV-2: every row shares the same Funzione aziendale, in both
    // management modes — a fresh creation, so nothing to grandfather (D-5
    // only applies to the work panel's edits of a persisted record).
    const businessFunctionIds = new Set(
      rows.map((row) => row.business_function_id).filter((id): id is number => id !== null),
    )
    if (businessFunctionIds.size > 1) {
      ctx.addIssue({
        code: 'custom',
        message: t('requestManagement.form.create.validation.businessFunctionMismatch'),
      })
    }
  })
}

/**
 * Client-side schema for the request-creation form (spec 0057). Only
 * `registry_id`/`product_lines` are RHF-validated fields: the client identity/
 * contacts/address stay a plain buffered draft (mirrors the registries create
 * form, `use-registry-form.ts`'s `profileDraft`) since the reused
 * `PersonalDataCardForm`/`ContactsManager`/`AddressCreateField` components
 * validate themselves inline and are not RHF-connected — `useRequestCreateForm`
 * gates the submit on their own validity instead (D-2's mutually-exclusive
 * anagrafica branches).
 *
 * `attributes` come from `POST /request-management/form-context` (user
 * directive 2026-08-07): they are what the create form renders its
 * "Informazioni aggiuntive" from, so the schema is REBUILT whenever the chosen
 * categories change — exactly as the work panel rebuilds its own from the
 * loaded panel.
 */
export function buildRequestCreateSchema(t: TFunction, attributes: ApplicableAttribute[] = []) {
  const codes = attributes.map((attribute) => attribute.code)
  const requiredCodes = attributes.filter((attribute) => attribute.is_required).map((attribute) => attribute.code)

  return z.object({
    registry_id: z.number().nullable(),
    product_lines: buildProductLinesSchema(t),
    // Initial attribution: plain relation ids — existence is a server-side
    // rule, there is nothing to mirror here (same as the work panel's own
    // attribution schema). Fonte is REQUIRED (user directive 2026-07-29),
    // mirroring StoreRequestRequest's own `required` rule; the other two stay
    // nullable. The explicit `: boolean` on the predicate is load-bearing:
    // TS 5.5+ infers an automatic type predicate and would narrow the field
    // to a non-nullable `number`, breaking every consumer typed against the
    // nullable form value (same note as `opportunity-schema.ts`).
    source_id: z
      .number()
      .nullable()
      .refine((value): boolean => value !== null, t('requestManagement.form.create.validation.sourceRequired')),
    reporter_id: z.number().nullable(),
    // The GA2 "Operatore" (user directive 2026-07-29): only submitted by an
    // actor holding `request-management.assignOperator` — the field is not
    // even rendered otherwise, and the endpoint rejects it server-side.
    operator_id: z.number().nullable(),
    // Sede operativa (spec 0056, user directive 2026-07-31): optional, and
    // what scopes the operator list — the same reciprocal link the work panel
    // and the Lead form already carry.
    operational_site_id: z.number().nullable(),
    // "Prodotti di interesse" (user directive 2026-07-31): available already
    // at creation, and OPTIONAL — the operator often records them only after
    // the first call. Their coherence with `product_lines` (every product must
    // belong to a chosen categoria prodotto) is a server-side rule: the
    // picker's own scope is what prevents it here, since a product's category
    // is not part of what the for-select options carry.
    products_of_interest: z.array(z.number()),
    // "Linee dell'offerta" (user directive 2026-08-07): the rows of the
    // Offerta this creation also opens, validated by the SAME per-row schema
    // the Offerte form and the work panel use. Optional as a COLLECTION (a
    // request often starts with none, spec 0086 AC-028), strict per row: a
    // half-filled row is not a line.
    offer_lines: z.array(quoteLineRowSchema(t)).max(MAX_OFFER_LINES, t('quotes.form.linesMax')),
    // Spec 0059 D-3: reward assignments for the reporter. Only the type id
    // travels (beneficiary/date are server-derived); duplicates are prevented
    // by the add control, which excludes already-picked types.
    rewards: z.array(z.object({ reward_type_id: z.number() })),
    // The operative fields the work panel edits, available at creation too
    // (user directive 2026-07-31). All optional: a request can still be
    // opened knowing nothing but its anagrafica and its product lines.
    next_callback_at: z.string().nullable(),
    general_notes: z.string(),
    // "Informazioni aggiuntive" (user directive 2026-08-07): one key per
    // applicable `code`, per-type shape from the SHARED builder — the same one
    // the work panel and the Offerta form use.
    attribute_values: buildAttributeValuesSchema(attributes, t) as unknown as TypedAttributeValuesSchema,
  }).superRefine((values, ctx) => {
    // The required dynamic fields, on the SAME gate `buildRequestCreatePayload`
    // uses to decide whether the map travels at all — mirroring both the panel
    // and the server, which checks `is_required` only on submitted codes. A
    // request whose category declares a required attribute therefore stays
    // creatable while the operator still knows nothing about it (the whole
    // point of a preliminary-information module), and becomes strict as soon
    // as they start filling the block in.
    if (!attributeValuesFilled(values.attribute_values, codes)) {
      return
    }

    for (const code of requiredCodes) {
      if (isEmptyCustomFieldValue(values.attribute_values[code])) {
        ctx.addIssue({
          code: 'custom',
          path: ['attribute_values', code],
          message: t('requestManagement.workPanel.validation.required', {
            defaultValue: 'This field is required.',
          }),
        })
      }
    }
  })
}

export type RequestCreateFormValues = z.infer<ReturnType<typeof buildRequestCreateSchema>>
