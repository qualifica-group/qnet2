import type { TFunction } from 'i18next'
import type { TaskTemplateStageErrors, TaskTemplateStageFormRow } from '@/features/task-templates/types'

/** Backend `stages.*.name` limit (`max:191`). */
export const STAGE_NAME_MAX_LENGTH = 191
/** Backend `stages` array ceiling (`max:50`). */
export const STAGES_MAX_COUNT = 50

export interface TaskTemplateStagesValidationResult {
  errors: TaskTemplateStageErrors
  /** Form-level message (the row COUNT itself is invalid), or `null` when the array size is fine. */
  formError: string | null
}

/**
 * Client-side mirror of the backend `stages[]` validation (spec 0146
 * `data_contract`, `ValidatesTaskTemplateStages::stagesRules`). Rows are
 * local state, not an RHF field array, so they are validated here — mirrors
 * `validateTaskTemplateItemRows`. An EMPTY array is legitimate (D-2: a
 * template may have no fasi at all), so there is no minimum-count check.
 */
export function validateTaskTemplateStageRows(
  rows: TaskTemplateStageFormRow[],
  t: TFunction,
): TaskTemplateStagesValidationResult {
  const errors: TaskTemplateStageErrors = {}

  if (rows.length > STAGES_MAX_COUNT) {
    return { errors, formError: t('taskTemplates.form.items.tooMany', { max: STAGES_MAX_COUNT }) }
  }

  for (const row of rows) {
    if (row.name.trim() === '') {
      errors[row.id] = { name: t('taskTemplates.form.nameRequired') }
    } else if (row.name.length > STAGE_NAME_MAX_LENGTH) {
      errors[row.id] = { name: t('taskTemplates.form.nameMax') }
    }
  }

  return {
    errors,
    formError: Object.keys(errors).length > 0 ? t('taskTemplates.form.items.hasErrors') : null,
  }
}

/** Matches a backend `stages.N.field` validation key. */
const STAGE_ERROR_KEY_PATTERN = /^stages\.(\d+)\.(\w+)$/

/**
 * Maps a 422 response's flat `stages.N.field` keys onto the row that
 * occupied position `N` in the array actually sent (`orderedStageIds`,
 * positional) — mirrors `extractItemServerErrors`.
 */
export function extractStageServerErrors(
  errors: Record<string, string[]> | undefined,
  orderedStageIds: string[],
): TaskTemplateStageErrors {
  const result: TaskTemplateStageErrors = {}
  if (!errors) {
    return result
  }

  for (const [key, messages] of Object.entries(errors)) {
    const match = STAGE_ERROR_KEY_PATTERN.exec(key)
    if (!match || messages.length === 0) {
      continue
    }
    const index = Number(match[1])
    const field = match[2] === 'id' ? 'id' : 'name'
    const rowId = orderedStageIds[index]
    if (!rowId) {
      continue
    }
    result[rowId] = { ...result[rowId], [field]: messages[0] }
  }

  return result
}
