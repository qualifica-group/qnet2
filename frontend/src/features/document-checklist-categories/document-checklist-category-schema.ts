import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema of the category form (spec 0410 AC-018), mirroring the backend
 * rules. The optional description is a plain string in the form ('' = empty)
 * and becomes null in the payload.
 */

export const CATEGORY_NAME_MAX_LENGTH = 255
export const CATEGORY_DESCRIPTION_MAX_LENGTH = 2000
export const CATEGORY_SORT_ORDER_MIN = 0
export const CATEGORY_SORT_ORDER_MAX = 65535

export function buildDocumentChecklistCategorySchema(t: TFunction) {
  const sortOrderRange = t('documentChecklistCategories.validation.sortOrderRange')
  return z.object({
    name: z
      .string()
      .min(1, t('documentChecklistCategories.validation.nameRequired'))
      .max(CATEGORY_NAME_MAX_LENGTH, t('documentChecklistCategories.validation.nameMax')),
    description: z
      .string()
      .max(CATEGORY_DESCRIPTION_MAX_LENGTH, t('documentChecklistCategories.validation.descriptionMax')),
    sort_order: z
      .number(sortOrderRange)
      .int(sortOrderRange)
      .min(CATEGORY_SORT_ORDER_MIN, sortOrderRange)
      .max(CATEGORY_SORT_ORDER_MAX, sortOrderRange),
  })
}

export type DocumentChecklistCategoryFormValues = z.infer<ReturnType<typeof buildDocumentChecklistCategorySchema>>
