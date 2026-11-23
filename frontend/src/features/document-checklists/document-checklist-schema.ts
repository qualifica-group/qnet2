import { z } from 'zod'
import type { TFunction } from 'i18next'
import { DOCUMENT_CHECKLIST_FILE_TYPES } from '@/features/document-checklists/types'

/**
 * Zod schema of the document checklist form (spec 0403 AC-023), mirroring the
 * backend rules. Optional text fields are plain strings in the form ('' = empty)
 * and become null in the payload (see `document-checklist-form-values.ts`).
 */

export const CHECKLIST_NAME_MAX_LENGTH = 255
export const CHECKLIST_CODE_MAX_LENGTH = 50
export const CHECKLIST_CODE_PATTERN = /^[A-Za-z0-9_-]+$/
export const CHECKLIST_DESCRIPTION_MAX_LENGTH = 2000
export const CHECKLIST_MAX_ITEMS = 200

export const ITEM_NAME_MAX_LENGTH = 255
export const ITEM_DESCRIPTION_MAX_LENGTH = 2000
export const ITEM_SECTION_MAX_LENGTH = 255
export const ITEM_VALIDATION_PROMPT_MAX_LENGTH = 5000
export const ITEM_MAX_FILE_SIZE_KB = 20480
export const ITEM_DEFAULT_FILE_SIZE_KB = 10240
export const ITEM_MAX_FILES = 10
export const ITEM_MAX_AGE_DAYS = 3650

/** Integer within [min, max], one message for every violation. */
function boundedInt(min: number, max: number, message: string) {
  return z.number(message).int(message).min(min, message).max(max, message)
}

function buildItemSchema(t: TFunction) {
  const filesRange = t('documentChecklists.validation.filesRange')
  return z
    .object({
      server_id: z.number().nullable(),
      name: z
        .string()
        .min(1, t('documentChecklists.validation.itemNameRequired'))
        .max(ITEM_NAME_MAX_LENGTH, t('documentChecklists.validation.itemNameMax')),
      description: z.string().max(ITEM_DESCRIPTION_MAX_LENGTH, t('documentChecklists.validation.itemDescriptionMax')),
      section: z.string().max(ITEM_SECTION_MAX_LENGTH, t('documentChecklists.validation.sectionMax')),
      is_required: z.boolean(),
      allowed_file_types: z
        .array(z.enum(DOCUMENT_CHECKLIST_FILE_TYPES))
        .min(1, t('documentChecklists.validation.fileTypesRequired'))
        .refine((types) => new Set(types).size === types.length, t('documentChecklists.validation.fileTypesDistinct')),
      max_file_size_kb: boundedInt(1, ITEM_MAX_FILE_SIZE_KB, t('documentChecklists.validation.fileSizeRange')),
      min_files: boundedInt(1, ITEM_MAX_FILES, filesRange),
      max_files: boundedInt(1, ITEM_MAX_FILES, filesRange),
      validation_prompt: z
        .string()
        .max(ITEM_VALIDATION_PROMPT_MAX_LENGTH, t('documentChecklists.validation.validationPromptMax')),
      max_age_days: boundedInt(1, ITEM_MAX_AGE_DAYS, t('documentChecklists.validation.maxAgeRange')).nullable(),
    })
    .refine((item) => item.max_files >= item.min_files, {
      message: t('documentChecklists.validation.maxFilesBelowMin'),
      path: ['max_files'],
    })
}

/** Create/edit schema of the checklist (same shape; the server rules the locked code). */
export function buildDocumentChecklistSchema(t: TFunction) {
  return z.object({
    name: z
      .string()
      .min(1, t('documentChecklists.validation.nameRequired'))
      .max(CHECKLIST_NAME_MAX_LENGTH, t('documentChecklists.validation.nameMax')),
    code: z
      .string()
      .min(1, t('documentChecklists.validation.codeRequired'))
      .max(CHECKLIST_CODE_MAX_LENGTH, t('documentChecklists.validation.codeMax'))
      .regex(CHECKLIST_CODE_PATTERN, t('documentChecklists.validation.codeFormat')),
    description: z.string().max(CHECKLIST_DESCRIPTION_MAX_LENGTH, t('documentChecklists.validation.descriptionMax')),
    category_id: z.number().nullable(),
    is_active: z.boolean(),
    items: z.array(buildItemSchema(t)).max(CHECKLIST_MAX_ITEMS, t('documentChecklists.validation.itemsMax')),
  })
}

export type DocumentChecklistFormValues = z.infer<ReturnType<typeof buildDocumentChecklistSchema>>
export type DocumentChecklistItemFormValues = DocumentChecklistFormValues['items'][number]
