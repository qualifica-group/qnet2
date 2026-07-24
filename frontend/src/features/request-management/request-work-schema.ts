import { z } from 'zod'
import type { TFunction } from 'i18next'
import { isEmptyCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import { buildContactSchema } from '@/features/personal-data/contact-schema'
import { buildPersonalDataSchema } from '@/features/personal-data/personal-data-schema'
import type { AddressDraft, ContactDraft, PersonalDataDraft } from '@/features/personal-data/types'
import type { ApplicableAttribute, RequestWorkflowStatusRef } from '@/features/request-management/types'

/**
 * Client-side schema for the work panel's editable surface (spec 0049
 * AC-062/063): the working-state select and the dynamic `attribute_values`
 * map, one entry per `applicable_attributes` row, keyed by `code`. MIRRORS the
 * backend's `AttributeValueValidator` (per-type rule + `is_required`), it does
 * not replace it — the server stays authoritative (406/422 still applies).
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

/** Builds the dynamic `attribute_values` shape, one key per applicable attribute `code`. */
function buildAttributeValuesSchema(attributes: ApplicableAttribute[], t: TFunction) {
  const shape: Record<string, z.ZodTypeAny> = {}
  for (const attribute of attributes) {
    shape[attribute.code] = buildAttributeScalarSchema(attribute, t)
  }

  const requiredCodes = attributes.filter((attribute) => attribute.is_required).map((attribute) => attribute.code)

  return z.object(shape).superRefine((values, ctx) => {
    for (const code of requiredCodes) {
      if (isEmptyCustomFieldValue((values as Record<string, unknown>)[code])) {
        ctx.addIssue({
          code: 'custom',
          path: [code],
          message: t('requestManagement.workPanel.validation.required', {
            defaultValue: 'This field is required.',
          }),
        })
      }
    }
  })
}

/**
 * `buildAttributeValuesSchema` derives its shape from a runtime-keyed
 * `Record<string, ZodTypeAny>`, so Zod infers it as `Record<string, unknown>`
 * — re-typed to the real value domain (mirrors `asCustomFieldsField`) so it
 * embeds cleanly under the form's `attribute_values` key.
 */
type TypedAttributeValuesSchema = z.ZodType<Record<string, CustomFieldValue>, Record<string, CustomFieldValue>>

/**
 * The client's buffered contacts. Each row is validated by the SAME
 * `buildContactSchema` the contact dialog uses (per-type value rules), so the
 * inline quick fields, the dialog and this submit gate never drift; issues are
 * re-pathed to `client_contacts.<index>.<field>`.
 */
function buildClientContactsSchema(t: TFunction) {
  const row = buildContactSchema(t)

  return z.array(z.custom<ContactDraft>()).superRefine((contacts, ctx) => {
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
        ctx.addIssue({ code: 'custom', path: [index, ...issue.path], message: issue.message })
      }
    })
  })
}

/**
 * The client's single buffered address, as a 0-or-1 array (the shape
 * `AddressCreateField` reads): empty means "nothing typed", so the field is
 * optional. Once a row exists only `line1` is required — mirroring the
 * backend, which keeps the city optional on update so a legacy address whose
 * city was never captured stays saveable.
 */
function buildClientAddressSchema(t: TFunction) {
  return z.array(z.custom<AddressDraft>()).superRefine((addresses, ctx) => {
    addresses.forEach((address, index) => {
      if (!address.line1) {
        ctx.addIssue({
          code: 'custom',
          path: [index, 'line1'],
          message: t('personalData.addresses.line1Required'),
        })
      }
    })
  })
}

/**
 * The client's buffered card identity, validated by the SAME
 * `buildPersonalDataSchema` the registries/users card form uses (per-type
 * required names, non-future birth date), so this panel and the anagraphic
 * modules never drift. `null` when the client has no card: nothing is
 * rendered and nothing is submitted, so there is nothing to validate.
 */
function buildClientIdentitySchema(t: TFunction) {
  const card = buildPersonalDataSchema(t)

  return z.custom<PersonalDataDraft | null>().superRefine((identity, ctx) => {
    if (!identity) {
      return
    }
    // The draft carries nulls where the card form carries empty strings.
    const result = card.safeParse({
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
      ctx.addIssue({ code: 'custom', path: issue.path, message: issue.message })
    }
  })
}

/**
 * Server-side rule (spec 0054 D-5, `RequestManagementService::updateWork()`):
 * a note is mandatory when the working status CHANGES to one flagged
 * `requires_note`. Mirrored here so the panel never round-trips to the
 * server just to learn its own selection needs a note — the server stays
 * authoritative (this check is anticipatory, not a replacement).
 */
export function buildRequestWorkSchema(
  attributes: ApplicableAttribute[],
  statuses: RequestWorkflowStatusRef[],
  originalStatusId: number | null,
  t: TFunction,
) {
  return z
    .object({
      opportunity_workflow_status_id: z.number().nullable(),
      next_callback_at: z.string().nullable(),
      note: z.string(),
      client_identity: buildClientIdentitySchema(t),
      client_contacts: buildClientContactsSchema(t),
      client_address: buildClientAddressSchema(t),
      // "Prodotti di interesse": a plain id set, MANDATORY since the user
      // directive 2026-07-23 (same rule as the opportunities form — the two
      // channels write the same collection). The other membership rules
      // (existence, category coverage) stay server-side only.
      products_of_interest: z.array(z.number()).min(1, t('products.ofInterest.required')),
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
      attribute_values: buildAttributeValuesSchema(attributes, t) as unknown as TypedAttributeValuesSchema,
    })
    .superRefine((values, ctx) => {
      const statusChanged = values.opportunity_workflow_status_id !== originalStatusId
      const targetStatus = statuses.find((status) => status.id === values.opportunity_workflow_status_id)

      if (statusChanged && targetStatus?.requires_note && values.note.trim() === '') {
        ctx.addIssue({
          code: 'custom',
          path: ['note'],
          message: t('requestManagement.workPanel.validation.noteRequired', {
            defaultValue: 'A note is required to move to this status.',
          }),
        })
      }
    })
}

export type RequestWorkFormValues = z.infer<ReturnType<typeof buildRequestWorkSchema>>
