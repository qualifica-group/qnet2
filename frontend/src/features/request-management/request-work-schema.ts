import { z } from 'zod'
import type { TFunction } from 'i18next'
import { isEmptyCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import { buildContactSchema } from '@/features/personal-data/contact-schema'
import { buildPersonalDataSchema } from '@/features/personal-data/personal-data-schema'
import type { Address, AddressDraft, ContactDraft, PersonalDataDraft } from '@/features/personal-data/types'
import type { ProductLineRow } from '@/features/product-lines/types'
import {
  buildAttributeValuesSchema,
  type TypedAttributeValuesSchema,
} from '@/features/request-management/attribute-values-schema'
import { quoteLineRowSchema } from '@/features/quotes/quote-schema'
import {
  attributeValuesChanged,
  clientAddressChanged,
  clientContactsChanged,
  clientIdentityChanged,
  productLinesChanged,
} from '@/features/request-management/request-work-payload'
import type {
  ApplicableAttribute,
  RequestClientIdentity,
  RequestContact,
} from '@/features/request-management/types'
import type { QuoteWorkflowStatusRef } from '@/features/quotes/types'

/**
 * Client-side schema for the work panel's editable surface (spec 0049
 * AC-062/063).
 */

/** Backend per-tab row ceiling (`max:200`), mirrored from `quote-schema.ts`. */
const MAX_OFFER_LINES = 200

/**
 * The three collectors below are invoked from the top-level refinement rather
 * than attached to their field: only there is it known whether the block is
 * going to travel at all (see `buildRequestWorkSchema`).
 *
 * The client's buffered contacts. Each row is validated by the SAME
 * `buildContactSchema` the contact dialog uses (per-type value rules), so the
 * inline quick fields, the dialog and this submit gate never drift; issues are
 * re-pathed to `client_contacts.<index>.<field>`.
 */
function addClientContactsIssues(contacts: ContactDraft[], ctx: z.RefinementCtx, t: TFunction): void {
  const row = buildContactSchema(t)

  contacts.forEach((contact, index) => {
    const result = row.safeParse({
      type: contact.type,
      value: contact.value,
      label: contact.label ?? '',
      is_primary: contact.is_primary,
    })
    if (result.success) {
      return
    }
    for (const issue of result.error.issues) {
      ctx.addIssue({ code: 'custom', path: ['client_contacts', index, ...issue.path], message: issue.message })
    }
  })
}

/**
 * The client's single buffered address, as a 0-or-1 array (the shape
 * `AddressCreateField` reads): empty means "nothing typed", so the field is
 * optional. Once a row exists only `line1` is required — mirroring the
 * backend, which keeps the city optional on update so a legacy address whose
 * city was never captured stays saveable.
 */
function addClientAddressIssues(addresses: AddressDraft[], ctx: z.RefinementCtx, t: TFunction): void {
  addresses.forEach((address, index) => {
    if (!address.line1) {
      ctx.addIssue({
        code: 'custom',
        path: ['client_address', index, 'line1'],
        message: t('personalData.addresses.line1Required'),
      })
    }
  })
}

/**
 * The client's buffered card identity, validated by the SAME
 * `buildPersonalDataSchema` the registries/users card form uses (per-type
 * required names, non-future birth date), so this panel and the anagraphic
 * modules never drift. `null` when the client has no card: nothing is
 * rendered and nothing is submitted, so there is nothing to validate.
 */
function addClientIdentityIssues(identity: PersonalDataDraft | null, ctx: z.RefinementCtx, t: TFunction): void {
  if (!identity) {
    return
  }

  // The draft carries nulls where the card form carries empty strings.
  const result = buildPersonalDataSchema(t).safeParse({
    type: identity.type,
    first_name: identity.first_name ?? '',
    last_name: identity.last_name ?? '',
    company_name: identity.company_name ?? '',
    tax_code: identity.tax_code ?? '',
    vat_number: identity.vat_number ?? '',
    sdi_code: identity.sdi_code ?? '',
    birth_date: identity.birth_date ?? '',
    gender: identity.gender ?? undefined,
  })
  if (result.success) {
    return
  }
  for (const issue of result.error.issues) {
    ctx.addIssue({ code: 'custom', path: ['client_identity', ...issue.path], message: issue.message })
  }
}

/**
 * The edited funzione/categoria rows (user directive 2026-07-31). MIRRORS the
 * create form's own rule (`buildRequestCreateSchema`) and the server's
 * `min:1` + per-row `required` ids: the collection may be replaced but never
 * emptied, and a half-filled row is not a line.
 */
function addProductLinesIssues(rows: ProductLineRow[], ctx: z.RefinementCtx, t: TFunction): void {
  if (rows.length === 0) {
    ctx.addIssue({
      code: 'custom',
      path: ['product_lines'],
      message: t('requestManagement.workPanel.validation.productLinesRequired'),
    })

    return
  }

  rows.forEach((row, index) => {
    if (row.business_function_id === null || row.product_category_id === null) {
      ctx.addIssue({
        code: 'custom',
        path: ['product_lines', index],
        message: t('requestManagement.workPanel.validation.productLineIncomplete'),
      })
    }
  })

  // Spec 0077 INV-2: every row shares the same Funzione aziendale, in both
  // management modes. The caller only invokes this function once
  // `productLinesChanged` is true (D-5), so a non-conformant historic
  // collection stays saveable for any unrelated edit (AC-044).
  const businessFunctionIds = new Set(
    rows.map((row) => row.business_function_id).filter((id): id is number => id !== null),
  )
  if (businessFunctionIds.size > 1) {
    ctx.addIssue({
      code: 'custom',
      path: ['product_lines'],
      message: t('requestManagement.workPanel.validation.businessFunctionMismatch'),
    })
  }
}

/**
 * The panel's loaded state, against which the sparse rules below are decided.
 * `UpdateRequestRequest` marks every key `sometimes`: an untouched key is
 * never validated server-side, so mirroring it unconditionally here would be
 * STRICTER than the endpoint — it would make a legacy record without a
 * product line unsavable for any unrelated edit, with the submit refused
 * before any request went out.
 */
export interface RequestWorkOriginalState {
  /** The persisted funzione/categoria pairs, in the form's own row shape. */
  product_lines: ProductLineRow[]
  /**
   * The persisted dynamic values, ALREADY seeded over the applicable codes
   * (`seedAttributeValues`) — the same baseline `buildRequestWorkPayload`
   * diffs against, so the schema validates exactly what is going to travel.
   */
  attribute_values: Record<string, unknown>
  /** The status the request currently holds: an unchanged one demands no note (spec 0083 AC-026). */
  quote_workflow_status_id: number | null
  /** The client blocks as the panel loaded them (`ValidatesRequestClientProfile` only sees what travels). */
  client_identity: RequestClientIdentity | null
  client_contacts: RequestContact[]
  client_address: Address | null
}

export function buildRequestWorkSchema(
  original: RequestWorkOriginalState,
  attributes: ApplicableAttribute[],
  statuses: QuoteWorkflowStatusRef[],
  t: TFunction,
) {
  const codes = attributes.map((attribute) => attribute.code)
  const requiredCodes = attributes.filter((attribute) => attribute.is_required).map((attribute) => attribute.code)

  return z
    .object({
      next_callback_at: z.string().nullable(),
      // The three buffered client blocks carry no field-level rule: they are
      // checked by the refinement below, which alone knows whether they travel.
      client_identity: z.custom<PersonalDataDraft | null>(),
      client_contacts: z.array(z.custom<ContactDraft>()),
      client_address: z.array(z.custom<AddressDraft>()),
      // "Funzione aziendale" + "categoria prodotto" (user directive
      // 2026-07-31): the same rows the create form edits, with the same two
      // rules (at least one row, every row complete) — but gated on the
      // collection actually being edited, like the two neighbours above: the
      // key does not travel otherwise, and a legacy request with no line
      // would become unsavable for any unrelated edit.
      product_lines: z.array(z.custom<ProductLineRow>()),
      // "Linee dell'offerta" (user directive 2026-08-07): the SAME per-row
      // schema the Offerte form validates its own `offer_lines` with — one
      // rule set for one endpoint contract. Unconditional, unlike the sparse
      // blocks around it: the persisted rows always satisfy it (the server
      // wrote them under the same rules), so there is nothing to grandfather,
      // and an incomplete row must block the submit whether or not the
      // collection is going to travel.
      offer_lines: z.array(quoteLineRowSchema(t)).max(MAX_OFFER_LINES, t('quotes.form.linesMax')),
      // Spec 0059 D-3: reward assignments for the reporter (chips under the
      // field). Only the type id travels — beneficiary/date are
      // server-derived. Duplicates are prevented client-side (the add
      // control excludes already-picked types).
      rewards: z.array(z.object({ reward_type_id: z.number() })),
      // Attribution (user directive 2026-07-22): plain nullable relation ids
      // — existence is a server-side rule, there is nothing to mirror here.
      source_id: z.number().nullable(),
      reporter_id: z.number().nullable(),
      operator_id: z.number().nullable(),
      // Spec 0056: facoltativa, same attribution shape.
      operational_site_id: z.number().nullable(),
      // "Informazioni aggiuntive" (user directive 2026-08-07): one key per
      // applicable `code`, per-type shape from the SHARED builder — the same
      // one the Offerta form and the create form use. `is_required` is added
      // by the refinement below, which alone knows whether the map travels.
      attribute_values: buildAttributeValuesSchema(attributes, t) as unknown as TypedAttributeValuesSchema,
      // "Stato di lavorazione" (user directive 2026-08-07): the Offerta's own
      // status plus the note a `requires_note` transition demands.
      quote_workflow_status_id: z.number().nullable(),
      note: z.string().nullable(),
    })
    .superRefine((values, ctx) => {
      // The required-attribute rule is gated on the map being sent at all —
      // the SAME predicate `buildRequestWorkPayload` uses, so the two cannot
      // drift. `AttributeValueValidator` checks `is_required` on SUBMITTED
      // codes only: mirroring it unconditionally would make a legacy request
      // missing a required attribute unsavable for any unrelated edit.
      if (attributeValuesChanged(values.attribute_values, original.attribute_values, codes)) {
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
      }

      // The transition note (spec 0083 AC-023/026), mirroring the server and
      // `buildUpdateQuoteSchema` exactly: demanded only when the TARGET row
      // differs from the one the request holds AND carries `requires_note`.
      const targetStatusId = values.quote_workflow_status_id
      if (
        targetStatusId !== null &&
        targetStatusId !== original.quote_workflow_status_id &&
        statuses.find((status) => status.id === targetStatusId)?.requires_note === true &&
        (values.note ?? '').trim() === ''
      ) {
        ctx.addIssue({
          code: 'custom',
          path: ['note'],
          message: t('quotes.form.noteRequired'),
        })
      }

      if (productLinesChanged(values.product_lines, original.product_lines)) {
        addProductLinesIssues(values.product_lines, ctx, t)
      }

      // Fonte is MANDATORY (user directive 2026-07-29) — and unlike the two
      // rules above this one is NOT gated on the key travelling: a request
      // without a source may not be saved at all, legacy rows included, which
      // is what the directive asks for. The server's own `sometimes|required`
      // covers the clearing half it can see.
      if (values.source_id === null) {
        ctx.addIssue({
          code: 'custom',
          path: ['source_id'],
          message: t('requestManagement.workPanel.validation.sourceRequired', {
            defaultValue: 'Select a source.',
          }),
        })
      }

      // Step 1: the buffered client blocks, on the same gate. A legacy card
      // whose tax code or VAT number does not pass the fiscal rules would
      // otherwise block every unrelated edit of the panel — while the server,
      // never receiving the block, has nothing to complain about.
      if (clientIdentityChanged(values.client_identity, original.client_identity)) {
        addClientIdentityIssues(values.client_identity, ctx, t)
      }

      if (clientContactsChanged(values.client_contacts, original.client_contacts)) {
        addClientContactsIssues(values.client_contacts, ctx, t)
      }

      if (clientAddressChanged(values.client_address, original.client_address)) {
        addClientAddressIssues(values.client_address, ctx, t)
      }
    })
}

export type RequestWorkFormValues = z.infer<ReturnType<typeof buildRequestWorkSchema>>
