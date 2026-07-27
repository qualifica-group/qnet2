import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  encodeName,
  encodeSurname,
  isFemaleTaxCode,
  isValidTaxCode,
  taxCodeBirthDate,
  taxCodeNameTriple,
  taxCodeSurnameTriple,
} from '@/lib/fiscal/tax-code'
import { isValidVatNumber } from '@/lib/fiscal/vat-number'

/**
 * Zod schema for the personal-data card form, built as a factory so validation
 * messages are localized via i18n. Mirrors the backend per-type contract:
 * an individual must carry first + last name, a company must carry a company
 * name; the birth date (if any) cannot be in the future.
 */
export function buildPersonalDataSchema(t: TFunction) {
  return z
    .object({
      type: z.enum(['individual', 'company']),
      first_name: z.string().optional(),
      last_name: z.string().optional(),
      company_name: z.string().optional(),
      tax_code: z.string().max(32).optional(),
      vat_number: z.string().max(32).optional(),
      // SDI recipient code (Codice Destinatario) — company e-invoicing only.
      sdi_code: z.string().max(32).optional(),
      // Empty string = "no date"; a value must be a real, non-future date.
      birth_date: z.string().optional(),
      // Individual only: the comune of birth, referenced by id (geo catalogue).
      birth_city_id: z.number().nullable().optional(),
      // Individual only (default male); a company card carries no gender.
      gender: z.enum(['male', 'female']).optional(),
    })
    .superRefine((values, ctx) => {
      if (values.type === 'individual') {
        if (!values.first_name) {
          ctx.addIssue({
            code: 'custom',
            path: ['first_name'],
            message: t('personalData.form.firstNameRequired'),
          })
        }
        if (!values.last_name) {
          ctx.addIssue({
            code: 'custom',
            path: ['last_name'],
            message: t('personalData.form.lastNameRequired'),
          })
        }
      }

      if (values.type === 'company' && !values.company_name) {
        ctx.addIssue({
          code: 'custom',
          path: ['company_name'],
          message: t('personalData.form.companyNameRequired'),
        })
      }

      if (values.birth_date && new Date(values.birth_date) >= startOfToday()) {
        ctx.addIssue({
          code: 'custom',
          path: ['birth_date'],
          message: t('personalData.form.birthDateFuture'),
        })
      }

      addFiscalIssues(values, ctx, t)
    })
}

/** The card fields the fiscal identifiers are checked against. */
interface FiscalFields {
  type: 'individual' | 'company'
  first_name?: string
  last_name?: string
  tax_code?: string
  vat_number?: string
  birth_date?: string
  gender?: 'male' | 'female'
}

/**
 * Mirrors the backend `TaxCode`/`VatNumber` rules: an individual carries the
 * sixteen-character personal code, a legal entity the eleven-digit numeric
 * one, and an individual's code must also be CONSISTENT with the anagraphic
 * fields of the same card. A blank identifier is always accepted — both stay
 * optional.
 */
function addFiscalIssues(
  values: FiscalFields,
  ctx: z.RefinementCtx,
  t: TFunction,
): void {
  const taxCode = values.tax_code?.trim()

  if (taxCode) {
    if (values.type === 'company') {
      if (!isValidVatNumber(taxCode)) {
        addTaxCodeIssue(ctx, t('personalData.form.companyTaxCodeInvalid'))
      }
    } else if (!isValidTaxCode(taxCode)) {
      addTaxCodeIssue(ctx, t('personalData.form.taxCodeInvalid'))
    } else {
      addTaxCodeMismatchIssue(taxCode, values, ctx, t)
    }
  }

  const vatNumber = values.vat_number?.trim()

  if (vatNumber && !isValidVatNumber(vatNumber)) {
    ctx.addIssue({
      code: 'custom',
      path: ['vat_number'],
      message: t('personalData.form.vatNumberInvalid'),
    })
  }
}

/** Reports the FIRST anagraphic field the code diverges from, so the message names it. */
function addTaxCodeMismatchIssue(
  taxCode: string,
  values: FiscalFields,
  ctx: z.RefinementCtx,
  t: TFunction,
): void {
  if (
    values.last_name &&
    encodeSurname(values.last_name) !== taxCodeSurnameTriple(taxCode)
  ) {
    addTaxCodeIssue(ctx, t('personalData.form.taxCodeLastNameMismatch'))

    return
  }

  if (
    values.first_name &&
    encodeName(values.first_name) !== taxCodeNameTriple(taxCode)
  ) {
    addTaxCodeIssue(ctx, t('personalData.form.taxCodeFirstNameMismatch'))

    return
  }

  if (values.birth_date && !birthDateMatches(taxCode, values.birth_date)) {
    addTaxCodeIssue(ctx, t('personalData.form.taxCodeBirthDateMismatch'))

    return
  }

  if (values.gender) {
    const encoded = isFemaleTaxCode(taxCode) ? 'female' : 'male'

    if (encoded !== values.gender) {
      addTaxCodeIssue(ctx, t('personalData.form.taxCodeGenderMismatch'))
    }
  }
}

/** The code carries no century, so the year is compared modulo 100. */
function birthDateMatches(taxCode: string, birthDate: string): boolean {
  const encoded = taxCodeBirthDate(taxCode)
  const [year, month, day] = birthDate.split('-').map(Number)

  if (encoded === null || Number.isNaN(year) || Number.isNaN(month) || Number.isNaN(day)) {
    return false
  }

  return year % 100 === encoded.year && month === encoded.month && day === encoded.day
}

function addTaxCodeIssue(ctx: z.RefinementCtx, message: string): void {
  ctx.addIssue({ code: 'custom', path: ['tax_code'], message })
}

/** Midnight today, so a `birth_date` equal to today is rejected (before:today). */
function startOfToday(): Date {
  const now = new Date()
  return new Date(now.getFullYear(), now.getMonth(), now.getDate())
}

export type PersonalDataFormValues = z.infer<
  ReturnType<typeof buildPersonalDataSchema>
>
