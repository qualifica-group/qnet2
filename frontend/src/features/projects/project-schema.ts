import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  asCustomFieldsField,
  type CustomFieldsSchema,
} from '@/features/custom-fields/build-custom-fields-schema'
import type { ProductLineRow } from '@/features/product-lines/types'

/**
 * Zod schema for the project create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function. The shape
 * mirrors the frozen backend contract (spec 0025) 1:1. `code` is optional and
 * manual-entry-in-create-only: the create payload includes it when valued,
 * the update payload never includes it (spec 0025 PARTE A).
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

/** Backend `code` column limit (`string(32)`). */
const CODE_MAX_LENGTH = 32

function baseFields(t: TFunction) {
  return {
    // Manual code (spec 0025): trimmed, required (the create form auto-fills
    // the next sequential suggestion, editable), max 32. Read-only in edit
    // (enforced by the field-permission ceiling); the server still generates
    // one as a fallback when absent.
    code: z
      .string()
      .trim()
      .min(1, t('projects.form.codeRequired'))
      .max(CODE_MAX_LENGTH, t('projects.form.codeMax')),
    name: z
      .string()
      .min(1, t('projects.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('projects.form.nameMax')),
    description: z.string().nullable(),
    // Nullable/optional (spec 0039 D-3): the server falls back to the system
    // "Nuovo" status when omitted, and the create form preselects it as soon
    // as the for-select resolves.
    pipeline_status_id: z.number().nullable(),
    // Geo cascade (spec 0027 BR-4): `country_id` required (withGeoHierarchyRule
    // below), the other three optional but parent-gated.
    country_id: z.number().nullable(),
    state_id: z.number().nullable(),
    province_id: z.number().nullable(),
    city_id: z.number().nullable(),
    // Spec 0094: replaces the former single `business_function_id`/
    // `product_category_id` pair with an inline-editable row collection
    // (mirrors the opportunity form, spec 0040 amendment rev.3). Each id is
    // individually nullable (a row starts empty and fills in place);
    // `withRequiredProductLinesRule` below requires at least one COMPLETE
    // row before submit, mirroring the backend's `required|min:1`.
    product_lines: z.array(
      z.object({
        business_function_id: z.number().nullable(),
        product_category_id: z.number().nullable(),
      }),
    ),
    partner_id: z.number().nullable(),
    // The Sede (spec directive 2026-07-21): the project's own, always
    // editable relation, inherited as a prefill by every campaign/lead
    // created under it (never a lock).
    operational_site_id: z.number().nullable(),
    // Date inputs hold `''` for "empty" (never `null`). `start_date` is
    // required; `end_date` is optional (converted to null at the payload
    // boundary), only its ordering vs `start_date` is enforced (BR-6).
    start_date: z.string().min(1, t('projects.form.startDateRequired')),
    end_date: z.string(),
    total_budget: z.number().nonnegative(t('projects.form.totalBudgetInvalid')).nullable(),
    target_lead: z.number().int().nonnegative(t('projects.form.targetLeadInvalid')).nullable(),
  }
}

/** BR-6: `end_date`, when set alongside `start_date`, must not be earlier. */
function withDateOrderRule<T extends z.ZodTypeAny>(schema: T, t: TFunction) {
  return schema.superRefine((values, ctx) => {
    const record = values as { start_date: string; end_date: string }
    if (record.start_date && record.end_date && record.end_date < record.start_date) {
      ctx.addIssue({
        code: 'custom',
        path: ['end_date'],
        message: t('projects.form.endDateBeforeStartDate'),
      })
    }
  })
}

/**
 * Spec 0094: at least one COMPLETE business-function + product-category row
 * is mandatory (mirrors the backend's `required|min:1`), reusing the shared
 * `productLines.*` i18n messages already frozen for the opportunity form
 * (spec 0040 amendment rev.3) rather than minting project-specific copies.
 */
function withRequiredProductLinesRule<T extends z.ZodTypeAny>(schema: T, t: TFunction) {
  return schema.superRefine((values, ctx) => {
    const record = values as { product_lines: ProductLineRow[] }
    if (record.product_lines.length === 0) {
      ctx.addIssue({ code: 'custom', path: ['product_lines'], message: t('productLines.required') })
      return
    }
    const hasIncompleteRow = record.product_lines.some(
      (row) => row.business_function_id === null || row.product_category_id === null,
    )
    if (hasIncompleteRow) {
      ctx.addIssue({ code: 'custom', path: ['product_lines'], message: t('productLines.rowIncomplete') })
    }
  })
}

/**
 * BR-4 (client-side part only): `country_id` is required, and a child level
 * may only be set when its parent is. The parent-BELONGS-TO-ancestor check
 * (e.g. state actually belongs to country) is server-side only — the client
 * has no such data (spec 0027 frontend note).
 */
function withGeoHierarchyRule<T extends z.ZodTypeAny>(schema: T, t: TFunction) {
  return schema.superRefine((values, ctx) => {
    const record = values as {
      country_id: number | null
      state_id: number | null
      province_id: number | null
      city_id: number | null
    }
    if (record.country_id === null) {
      ctx.addIssue({
        code: 'custom',
        path: ['country_id'],
        message: t('projects.form.countryRequired'),
      })
    }
    if (record.province_id !== null && record.state_id === null) {
      ctx.addIssue({
        code: 'custom',
        path: ['province_id'],
        message: t('projects.form.provinceRequiresState'),
      })
    }
    if (record.city_id !== null && record.state_id === null) {
      ctx.addIssue({
        code: 'custom',
        path: ['city_id'],
        message: t('projects.form.cityRequiresState'),
      })
    }
  })
}

/** Create schema. `customFieldsSchema` is the toolbox-built schema for `custom_fields` (spec 0021 AC-023). */
export function buildCreateProjectSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  const object = z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
  return withGeoHierarchyRule(withRequiredProductLinesRule(withDateOrderRule(object, t), t), t)
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateProjectSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return buildCreateProjectSchema(t, customFieldsSchema)
}

export type CreateProjectFormValues = z.infer<ReturnType<typeof buildCreateProjectSchema>>
export type UpdateProjectFormValues = CreateProjectFormValues
