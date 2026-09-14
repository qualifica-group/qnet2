import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema for the task template create/edit form's HEADER fields only
 * (`name`/`description`/`is_active`), built as a factory so validation
 * messages are localized. `items` is local state driven by
 * `<TaskTemplateItemsEditor>` (a `<SortableList>`, not an RHF field array —
 * mirrors `quote-workflow-schema`'s `statuses`), validated separately by
 * `validateTaskTemplateItemRows`.
 */

/** Backend `task_templates.name` column limit (`max:191`, unique). */
const NAME_MAX_LENGTH = 191

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .trim()
      .min(1, t('taskTemplates.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('taskTemplates.form.nameMax')),
    description: z.string().nullable(),
    is_active: z.boolean(),
  }
}

/** Create schema. */
export function buildCreateTaskTemplateSchema(t: TFunction) {
  return z.object(baseFields(t))
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateTaskTemplateSchema(t: TFunction) {
  return z.object(baseFields(t))
}

export type CreateTaskTemplateFormValues = z.infer<ReturnType<typeof buildCreateTaskTemplateSchema>>
export type UpdateTaskTemplateFormValues = z.infer<ReturnType<typeof buildUpdateTaskTemplateSchema>>
