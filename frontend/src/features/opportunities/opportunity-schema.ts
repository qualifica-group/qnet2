import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema for the opportunity create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function. The shape
 * mirrors the opportunity contract while preserving the create/edit
 * distinction: supervisor is required only when creating an opportunity and
 * remains nullable when editing an existing one.
 */

/** Backend limit on FILLED manager slots (`max:4`, `ValidatesManagerSlots`, mirrors registries). */
export const MAX_MANAGERS = 4

/** Backend `estimated_value` column ceiling, `decimal(15,2)` (`max:9999999999999.99`). */
export const ESTIMATED_VALUE_MAX = 9999999999999.99

/** `success_probability` bounds (`unsignedTinyInteger`, BR-5: 0..100). */
export const SUCCESS_PROBABILITY_MIN = 0
export const SUCCESS_PROBABILITY_MAX = 100

/** Backend `general_notes` ceiling (`max:5000`), mirroring the lead `notes` it inherits from. */
export const GENERAL_NOTES_MAX_LENGTH = 5000

/**
 * A required relation id: `null` (unset) fails the refine. See
 * `lead-schema.ts` for why the explicit `: boolean` return type on the
 * predicate is load-bearing (TS 5.5+'s automatic type-predicate inference
 * would otherwise narrow the field to non-nullable `number`, breaking every
 * consumer typed against the nullable form value).
 */
function requiredRelationId(message: string) {
  return z
    .number()
    .nullable()
    .refine((value): boolean => value !== null, { message })
}

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    // D-4/D-5 (spec 0057): registry_id is the required identity field — the
    // name is no longer a form input, it is derived server-side as `OPP_{id}`.
    registry_id: requiredRelationId(t('opportunities.form.registryRequired')),
    // Spec 0043 D-3: the opportunity status is a mandatory FK, mirrors registry_id.
    opportunity_status_id: requiredRelationId(t('opportunities.form.opportunityStatusRequired')),
    referent_id: z.number().nullable(),
    commercial_id: z.number().nullable(),
    reporter_id: z.number().nullable(),
    supervisor_id: z.number().nullable(),
    source_id: z.number().nullable(),
    // Spec 0056: facoltativa, never a submit-blocking requirement — no
    // server-side inheritance from another entity (a plain editable FK).
    operational_site_id: z.number().nullable(),
    // Spec 0047 (D1, AC-026): the Regione, freely settable on a standalone
    // opportunity (forced read-only in the UI when lead-linked, see
    // `OpportunityFormBody`). Never a submit-blocking requirement.
    state_id: z.number().nullable(),
    // Spec 0047 (AC-017): an optional manual override of the resolved
    // working-state; only ever rendered/submitted in edit mode (the create
    // select is hidden, the server resolves the initial row on its own).
    opportunity_workflow_status_id: z.number().nullable(),
    // Amendment rev.3 (AC-097/099): replaces the former single
    // `business_function_id`/`product_category_id` with an inline-editable
    // row collection (mirrors `manager_slots`: "Add" appends an EMPTY row).
    // Each id is individually nullable (a row starts empty and fills in
    // place), but `superRefine` below requires BOTH non-null per row before
    // submit — an incomplete row (including a freshly-added empty one)
    // blocks the form, surfaced as a single error on the collection. User
    // directive 2026-07-17: at least one row is REQUIRED (an empty collection
    // blocks submit), mirroring the backend `required|min:1`.
    product_lines: z
      .array(
        z.object({
          business_function_id: z.number().nullable(),
          product_category_id: z.number().nullable(),
        }),
      )
      .superRefine((rows, ctx) => {
        if (rows.length === 0) {
          ctx.addIssue({ code: z.ZodIssueCode.custom, message: t('productLines.required') })
          return
        }
        const hasIncompleteRow = rows.some(
          (row) => row.business_function_id === null || row.product_category_id === null,
        )
        if (hasIncompleteRow) {
          ctx.addIssue({ code: z.ZodIssueCode.custom, message: t('productLines.rowIncomplete') })
        }
      }),
    // "Prodotti di interesse": a plain id set, MANDATORY since the user
    // directive 2026-07-23 (at least one product, mirroring `product_lines`).
    // Cross-category picks are LEGAL (the server adds the matching product
    // line, after the picker's unlock dialog), so there is nothing else to
    // cross-validate against `product_lines` here.
    products_of_interest: z.array(z.number()).min(1, t('products.ofInterest.required')),
    // Spec 0059 D-3: reward assignments for the reporter (chips under the
    // field). Only the type id travels — beneficiary/date are server-derived.
    // Duplicates are prevented client-side (the add control excludes
    // already-picked types), so there is nothing left to cross-validate here.
    rewards: z.array(z.object({ reward_type_id: z.number() })),
    // Ordered, gap-aware "G.A. n" manager slots: index+1 = G.A. number, `null`
    // = an intentionally empty slot. At most MAX_MANAGERS filled.
    manager_slots: z
      .array(z.number().nullable())
      .refine(
        (slots) => slots.filter((slot) => slot !== null).length <= MAX_MANAGERS,
        t('opportunities.form.managersMax'),
      ),
    start_date: z.string().nullable(),
    expected_close_date: z.string().nullable(),
    estimated_value: z
      .number()
      .nonnegative(t('opportunities.form.estimatedValueInvalid'))
      .max(ESTIMATED_VALUE_MAX, t('opportunities.form.estimatedValueInvalid'))
      .nullable(),
    // Spec 0040 A-6: rendered as a 0..100 slider that always holds a value
    // (default 0), so this is a plain non-nullable integer — "0%" ≡ "not set".
    success_probability: z
      .number()
      .int()
      .min(SUCCESS_PROBABILITY_MIN, t('opportunities.form.successProbabilityInvalid'))
      .max(SUCCESS_PROBABILITY_MAX, t('opportunities.form.successProbabilityInvalid')),
    // "Note generali" (user directive 2026-07-27): free text prefilled from
    // the originating lead's own notes, always editable/clearable (never
    // BR-2-locked).
    general_notes: z
      .string()
      .max(GENERAL_NOTES_MAX_LENGTH, t('opportunities.form.generalNotesMax'))
      .nullable(),
  }
}

/**
 * Create schema. Directive 2026-07-21: `supervisor_id` is no longer required
 * on create either — it derives from the linked Lead's Operatore, which may
 * now be empty — so create and edit share the exact same (nullable) shape.
 */
export function buildCreateOpportunitySchema(t: TFunction) {
  return z.object(baseFields(t))
}

/** Edit schema; partial PATCH is computed by the caller and supervisor remains nullable. */
export function buildUpdateOpportunitySchema(t: TFunction) {
  return z.object(baseFields(t))
}

export type CreateOpportunityFormValues = z.infer<ReturnType<typeof buildCreateOpportunitySchema>>
export type UpdateOpportunityFormValues = CreateOpportunityFormValues
