import { z } from 'zod'
import type { TFunction } from 'i18next'
import type { DocumentLayoutConfig } from '@/features/document-layouts/layout-config'
import { DOCUMENT_LAYOUT_MODULES } from '@/features/document-layouts/types'

/**
 * Zod schema for the document layout metadata create/edit form (spec 0069
 * `validation`), built as a factory so validation messages are localized via
 * the i18n `t` function. Mirrors the frozen backend contract 1:1 — same
 * cloned shape as `payment-methods/payment-method-schema.ts` (spec 0068,
 * the CRUD reference). `code` and `module` keep the same shape in both
 * create and edit schemas: on edit both render disabled (backend field
 * permissions report them readonly outside create) and
 * `buildUpdatePayload` never emits them, so the value here is always the
 * server's own, already-valid one. `config` (the block/zone tree) is a form
 * value owned by the visual editor, so a cancelled row or a discarded draft
 * restores it like any other field; its shape is the server's to validate
 * (path-based 422, AC-129), this schema only guarantees it is present.
 */

/** Backend `name` column limit (`max:191`). */
export const NAME_MAX_LENGTH = 191
/** Backend `code` column limit (`max:64`). */
export const CODE_MAX_LENGTH = 64
/** Backend `code` shape: snake_case identifier (spec engineering.md §1.2). */
const CODE_PATTERN = /^[a-z][a-z0-9_]*$/
/** Backend `description` column limit (`max:500`). */
const DESCRIPTION_MAX_LENGTH = 500

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('documentLayouts.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('documentLayouts.form.nameMax')),
    code: z
      .string()
      .min(1, t('documentLayouts.form.codeRequired'))
      .max(CODE_MAX_LENGTH, t('documentLayouts.form.codeMax'))
      .regex(CODE_PATTERN, t('documentLayouts.form.codeInvalid')),
    description: z
      .string()
      .max(DESCRIPTION_MAX_LENGTH, t('documentLayouts.form.descriptionMax'))
      .nullable(),
    module: z.enum(DOCUMENT_LAYOUT_MODULES, {
      error: t('documentLayouts.form.moduleRequired'),
    }),
    is_active: z.boolean(),
    is_default: z.boolean(),
    config: z.custom<DocumentLayoutConfig>((value) => typeof value === 'object' && value !== null),
  }
}

/** Create schema. */
export function buildCreateDocumentLayoutSchema(t: TFunction) {
  return z.object(baseFields(t))
}

/** Edit schema (same shape; `code`/`module` render disabled and are never diffed into the PATCH payload). */
export function buildUpdateDocumentLayoutSchema(t: TFunction) {
  return z.object(baseFields(t))
}

/** Clone dialog schema (spec 0339): the copy's own identity, same rules as on create. */
export function buildCloneDocumentLayoutSchema(t: TFunction) {
  const { name, code } = baseFields(t)
  return z.object({ name, code })
}

export type CreateDocumentLayoutFormValues = z.infer<
  ReturnType<typeof buildCreateDocumentLayoutSchema>
>
export type UpdateDocumentLayoutFormValues = z.infer<
  ReturnType<typeof buildUpdateDocumentLayoutSchema>
>
export type CloneDocumentLayoutFormValues = z.infer<ReturnType<typeof buildCloneDocumentLayoutSchema>>
