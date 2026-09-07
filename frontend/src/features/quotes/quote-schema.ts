import { z } from 'zod'
import type { TFunction } from 'i18next'
import { COMMISSION_ROLES, COMMISSION_TYPES } from '@/features/commission-configurations/types'
import { MAX_MANAGER_SLOTS } from '@/components/form/manager-slots-limits'
import {
  buildAttributeValuesSchema,
  type TypedAttributeValuesSchema,
} from '@/features/request-management/attribute-values-schema'
import { isPristineLineRow } from '@/features/quotes/quote-line-values'
import { isEmptyCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { ApplicableAttributeSummary, QuoteWorkflowStatusRef } from '@/features/quotes/types'

/**
 * Zod schema for the quote create/edit form, built as a factory so validation
 * messages are localized via the i18n `t` function (namespace `quotes.form.*`).
 * The shape mirrors the spec 0065 frozen `data_contract` 1:1, including the
 * per-row validation of `offer_lines`/`cost_lines` (AC-075): each row is its
 * own `z.object(...).superRefine(...)`, so a violation lands on that exact
 * row's field (`offer_lines.<index>.quantity`), not on the array as a whole —
 * required for the field-level `aria-describedby`/`aria-invalid` wiring the
 * form owns.
 */

/** Backend `title` column limit (`max:191`). */
const TITLE_MAX_LENGTH = 191

/** Backend `code` column limit (`string(32)`), mirrors the product/project pattern (D-13/D-1b). */
const CODE_MAX_LENGTH = 32

/** Backend `internal_notes` column limit (`max:5000`). */
const INTERNAL_NOTES_MAX_LENGTH = 5000

/** Backend per-tab row ceiling (`max:200`, AC-035). */
const MAX_LINES_PER_TAB = 200

/**
 * Spec 0087 (D-1/D-11): the offer's own "G.A. n" manager slot ceiling,
 * shared with `opportunity-schema.ts`/`registry-schema.ts` via the single
 * neutral `MAX_MANAGER_SLOTS` constant so the three never drift apart.
 */
export const MAX_MANAGERS = MAX_MANAGER_SLOTS

/**
 * How many empty G.A. rows the quote form opens on when nothing is
 * inherited yet (mirrors `opportunity-schema.ts`'s own default) — a UX
 * default, INDEPENDENT of `MAX_MANAGERS`.
 */
export const DEFAULT_MANAGER_SLOTS = 4

/** Backend `quantity` ceiling (`max:999999.99`). */
export const QUANTITY_MAX = 999999.99

/** Backend `unit_price` ceiling (`max:99999999.99`). */
export const UNIT_PRICE_MAX = 99999999.99

/**
 * `true` when `value` has at most 2 decimal digits (AC-032: `10.005` fails,
 * `10.01` passes). Shifts the decimal point via the number's own (exact,
 * round-trip) string form rather than a raw `value * 100` multiplication,
 * which would introduce IEEE-754 artifacts at the very magnitudes this check
 * cares about (e.g. `10.005 * 100 === 1000.4999999999999`). Mirrors the same
 * exponential-notation technique as `round2` in `quote-totals.ts`.
 */
function hasMaxTwoDecimals(value: number): boolean {
  const rounded = Number(`${Math.round(Number(`${value}e2`))}e-2`)
  return rounded === value
}

/**
 * A required relation id: `null` (unset) fails the refine. See
 * `opportunity-schema.ts` for why the explicit `: boolean` return type on the
 * predicate is load-bearing (TS 5.5+ automatic type-predicate inference would
 * otherwise narrow the field to non-nullable `number`).
 */
function requiredRelationId(message: string) {
  return z
    .number()
    .nullable()
    .refine((value): boolean => value !== null, { message })
}

/**
 * One `offer_lines`/`cost_lines` row (D-11: both tabs share this exact shape).
 * Held nullable-per-field so the controlled inputs can represent "empty"
 * while typing; `superRefine` enforces the backend's `required`/range/
 * decimal-precision rules per field, each issue keyed to its own path so it
 * survives the array wrapper untouched.
 *
 * Exported because Gestione Richieste writes the SAME `offer_lines` (user
 * directive 2026-08-07) and must validate them by the SAME rules — its own
 * rows simply never carry the optional `commissions` block, which that
 * endpoint prohibits.
 */
export function quoteLineRowSchema(t: TFunction) {
  return z
    .object({
      id: z.number().optional(),
      product_id: z.number().nullable(),
      quantity: z.number().nullable(),
      // Read-only display value congelated on the persisted line (spec 0088,
      // D-5): never editable, never part of `toLineInputs`'s wire shape
      // (quote-line-values.ts) — the backend rejects it as `prohibited`.
      // Optional: a freshly-added row has none until the line is saved.
      unit_of_measure: z
        .object({ id: z.number(), name: z.string(), symbol: z.string() })
        .nullable()
        .optional(),
      unit_price: z.number().nullable(),
      vat_rate_id: z.number().nullable(),
      commissions: z.array(z.object({
        id: z.number().optional(),
        recipient_role: z.enum(COMMISSION_ROLES),
        recipient_type: z.enum(['referent', 'user', 'registry']),
        recipient_id: z.number(),
        recipient: z.object({ id: z.number(), name: z.string() }).nullable().optional(),
        commission_type: z.enum(COMMISSION_TYPES),
        value: z.number().min(0),
        internal_note: z.string().max(5000).nullable(),
        origin: z.enum(['PRODUCT', 'PRODUCT_CATEGORY', 'RECIPIENT', 'MANUAL_OVERRIDE']),
        commission_configuration_id: z.number().nullable(),
      })).optional(),
    })
    .superRefine((row, ctx) => {
      // Una riga mai toccata non e' una riga: le create form ne aprono una
      // vuota di default (direttiva 2026-09-01) e un'offerta senza righe resta
      // legittima, quindi non deve impedire il submit. `toLineInputs` la scarta
      // dal payload con lo stesso predicato.
      if (isPristineLineRow(row)) {
        return
      }

      if (row.product_id === null) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          path: ['product_id'],
          message: t('quotes.form.lineProductRequired'),
        })
      }

      if (row.quantity === null) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          path: ['quantity'],
          message: t('quotes.form.lineQuantityRequired'),
        })
      } else if (row.quantity <= 0 || row.quantity > QUANTITY_MAX) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          path: ['quantity'],
          message: t('quotes.form.lineQuantityInvalid'),
        })
      } else if (!hasMaxTwoDecimals(row.quantity)) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          path: ['quantity'],
          message: t('quotes.form.lineQuantityDecimals'),
        })
      }

      if (row.unit_price === null) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          path: ['unit_price'],
          message: t('quotes.form.lineUnitPriceRequired'),
        })
      } else if (row.unit_price < 0 || row.unit_price > UNIT_PRICE_MAX) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          path: ['unit_price'],
          message: t('quotes.form.lineUnitPriceInvalid'),
        })
      } else if (!hasMaxTwoDecimals(row.unit_price)) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          path: ['unit_price'],
          message: t('quotes.form.lineUnitPriceDecimals'),
        })
      }
    })
}

/** Shared fields common to create and edit. */
function baseFields(t: TFunction, attributes: ApplicableAttributeSummary[]) {
  return {
    // Manual code (D-13/D-1b): trimmed, required (the create form auto-fills
    // the next sequential suggestion, editable), max 32. Read-only in edit
    // (field-permission ceiling); the server still generates one as a
    // fallback when absent.
    code: z
      .string()
      .trim()
      .min(1, t('quotes.form.codeRequired'))
      .max(CODE_MAX_LENGTH, t('quotes.form.codeMax')),
    title: z
      .string()
      .min(1, t('quotes.form.titleRequired'))
      .max(TITLE_MAX_LENGTH, t('quotes.form.titleMax')),
    // Immutable after create (AC-025); still part of both schemas since the
    // form always displays it (read-only in edit) and the create form must
    // block submit until it is set.
    opportunity_id: requiredRelationId(t('quotes.form.opportunityRequired')),
    // Spec 0083: the operational status of the quote, picked from the set the
    // backend resolved for it. Nullable on create — omitted means "assign the
    // `open` row of the resolved set" (AC-020).
    quote_workflow_status_id: z.number().nullable(),
    // Transition note. Never persisted on the quote: it becomes a note on the
    // parent opportunity's thread, and is mandatory only when the TARGET status
    // carries `requires_note` (AC-023). The requirement is a refine on the
    // update schema, not a field-level rule, because it depends on the set.
    note: z.string().max(INTERNAL_NOTES_MAX_LENGTH, t('quotes.form.noteMax')).nullable(),
    commercial_id: z.number().nullable(),
    reporter_id: z.number().nullable(),
    // Spec 0059 D-3, extended to the Offerta origin (directive 2026-08-31):
    // reward assignments for the Segnalatore, the chips under the reporter
    // field. The "non-empty requires a reporter" rule is NOT mirrored here:
    // the control is only mounted once there IS one, and the server owns the
    // 422 for the one case that can still reach it (a reporter cleared while
    // chips are attached).
    rewards: z.array(z.object({ reward_type_id: z.number() })),
    supervisor_id: z.number().nullable(),
    // Spec 0087 (D-1/D-11): ordered, gap-aware "G.A. n" manager slots,
    // mirrors `opportunity-schema.ts`. index+1 = G.A. number, `null` = an
    // intentionally empty slot. At most MAX_MANAGERS filled; the D-6
    // appartenenza rule (a slot's user must already be a G.A. of the linked
    // Opportunity) is server-side only — the client has no candidate list to
    // validate it against here.
    manager_slots: z
      .array(z.number().nullable())
      .refine(
        (slots) => slots.filter((slot) => slot !== null).length <= MAX_MANAGERS,
        t('quotes.form.managersMax', { max: MAX_MANAGERS }),
      ),
    // Societa'/Societa' Sede/Sede operativa (directive 2026-07-30): all three
    // optional. The site-belongs-to-company rule is enforced server-side
    // (ValidatesQuoteCompanySite) and mirrored here only as a UI cascade —
    // the picker is scoped to the chosen company, so no cross-field refine.
    company_id: z.number().nullable(),
    company_site_id: z.number().nullable(),
    operational_site_id: z.number().nullable(),
    // Document-generation layout (spec 0070 D-3): optional, no cross-field
    // refine — an inactive/mismatched layout is a server-side 422
    // (`ValidatesQuoteLayout`), the picker here is only an affordance.
    layout_id: z.number().nullable(),
    // Metodo di pagamento concordato (directive 2026-07-30): optional, lives
    // in the "Note e pagamenti" tab alongside `internal_notes`.
    payment_method_id: z.number().nullable(),
    internal_notes: z
      .string()
      .max(INTERNAL_NOTES_MAX_LENGTH, t('quotes.form.internalNotesMax'))
      .nullable(),
    offer_lines: z
      .array(quoteLineRowSchema(t))
      .max(MAX_LINES_PER_TAB, t('quotes.form.linesMax')),
    cost_lines: z
      .array(quoteLineRowSchema(t))
      .max(MAX_LINES_PER_TAB, t('quotes.form.linesMax')),
    // "Informazioni aggiuntive" (spec 0084, D-5): una chiave per `code`
    // applicabile, forma per-tipo dal builder CONDIVISO — lo stesso che usano
    // Prodotti e Gestione Richieste, cosi' una regola corretta su un canale
    // non puo' restare sbagliata sull'altro. La obbligatorieta' la aggiunge
    // ogni caller sotto: dipende dal fatto che la mappa viaggi o no.
    attribute_values: buildAttributeValuesSchema(attributes, t) as unknown as TypedAttributeValuesSchema,
  }
}

/** I `code` applicabili marcati `is_required`: gli unici che una refine impone. */
function requiredAttributeCodes(attributes: ApplicableAttributeSummary[]): string[] {
  return attributes.filter((attribute) => attribute.is_required).map((attribute) => attribute.code)
}

/** Un'issue "campo obbligatorio" per ogni `code` required rimasto vuoto. */
function addMissingRequiredAttributes(
  values: Record<string, CustomFieldValue>,
  codes: string[],
  t: TFunction,
  ctx: z.RefinementCtx,
): void {
  for (const code of codes) {
    if (isEmptyCustomFieldValue(values[code])) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['attribute_values', code],
        message: t('attributeValues.validation.required', { defaultValue: 'This field is required.' }),
      })
    }
  }
}

/**
 * `true` when at least one row is a real, touched line (spec 0102): a
 * pristine seeded row (`isPristineLineRow`) never counts, or the create form
 * — which opens on exactly one such row — could never pass an untouched
 * submit-time schema.
 */
function hasSubmittableLine(lines: QuoteLineFormValues[]): boolean {
  return lines.some((line) => !isPristineLineRow(line))
}

export function buildCreateQuoteSchema(t: TFunction, attributes: ApplicableAttributeSummary[] = []) {
  return z.object(baseFields(t, attributes)).superRefine((values, ctx) => {
    addMissingRequiredAttributes(values.attribute_values, requiredAttributeCodes(attributes), t, ctx)

    // Spec 0102 AC-001/002/040/041: creation always requires at least one
    // REVENUE line. The seeded `EMPTY_LINE_ROW` the create form opens on
    // (use-quote-form.ts) is pristine, not a row, so it never counts here —
    // the collection-level issue lands on `offer_lines` itself, not a row.
    if (!hasSubmittableLine(values.offer_lines)) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['offer_lines'],
        message: t('quotes.form.offerLinesRequired'),
      })
    }
  })
}

/**
 * Edit schema; partial PATCH is computed by the caller. Same shape as create
 * (opportunity_id/code stay read-only, enforced by the form's field
 * permissions, not the schema), plus the `requires_note` rule of spec 0083.
 *
 * The rule mirrors the server exactly (AC-023/AC-026) and therefore needs both
 * the resolved set — to look the TARGET row up — and the status the quote
 * currently holds: an unchanged status demands nothing, so re-saving a quote
 * already sitting on a `requires_note` row never asks for a note again.
 *
 * `originalHasOfferLines` mirrors the server's own gate (spec 0102 D-2/
 * AC-042/043): the backend 422s only when the `offer_lines` KEY travels and
 * is empty, and `buildUpdatePayload`/`sameLines` (quote-form-payload.ts) only
 * put that key on the wire when the row SET changed from what was loaded. An
 * Offerta that already had zero lines and stays untouched never sends the
 * key, so it must stay saveable on every other field (grandfathering) — the
 * client can only tell the two cases apart by knowing what the offer
 * originally had, since by submit time `values.offer_lines` is the user's
 * CURRENT (possibly edited) state, not the original one.
 */
export function buildUpdateQuoteSchema(
  t: TFunction,
  statuses: QuoteWorkflowStatusRef[],
  originalStatusId: number | null,
  originalHasOfferLines: boolean,
  attributes: ApplicableAttributeSummary[] = [],
) {
  return z.object(baseFields(t, attributes)).superRefine((values, ctx) => {
    addMissingRequiredAttributes(values.attribute_values, requiredAttributeCodes(attributes), t, ctx)

    if (originalHasOfferLines && !hasSubmittableLine(values.offer_lines)) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['offer_lines'],
        message: t('quotes.form.offerLinesRequired'),
      })
    }

    const targetId = values.quote_workflow_status_id

    if (targetId === null || targetId === originalStatusId) {
      return
    }

    const target = statuses.find((status) => status.id === targetId) ?? null

    if (target?.requires_note !== true || (values.note ?? '').trim() !== '') {
      return
    }

    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['note'],
      message: t('quotes.form.noteRequired'),
    })
  })
}

export type CreateQuoteFormValues = z.infer<ReturnType<typeof buildCreateQuoteSchema>>
export type UpdateQuoteFormValues = CreateQuoteFormValues

/**
 * Canonical form-values type consumed by `quote-form-payload.ts` and (once
 * built) `use-quote-form.ts` — kept here, not in the not-yet-existing form
 * hook, so the payload builder has no dependency on it. The hook's own
 * `QuoteFormValues` (if it declares one) must stay identical to this shape.
 */
export type QuoteFormValues = CreateQuoteFormValues

/** One row of `QuoteFormValues.offer_lines`/`cost_lines`. */
export type QuoteLineFormValues = QuoteFormValues['offer_lines'][number]
