import { normalizeText } from '@/features/imports/wizard/normalize-text'
import type {
  ValueMappingEntry,
  ValueMappingField,
  ValueMappings,
} from '@/features/imports/wizard/types'

/** Global field ids the value-mapping request depends on (spec 0348 contract). */
export const PRODUCT_CATEGORY_FIELD_ID = 'product_category_id'
export const SOURCE_FIELD_ID = 'source_id'

/** Value-mapping field that allows creating a new status and needs the product line. */
export const WORKFLOW_STATUS_FIELD_ID = 'workflow_status'

export const WORKFLOW_STATUS_GROUPS = ['open', 'pending', 'validated', 'closed_won', 'closed_lost'] as const

/** Stable empty fallbacks (hoisted so dependent memos keep their identity). */
export const EMPTY_VALUE_MAPPING_FIELDS: ValueMappingField[] = []
export const EMPTY_VALUE_MAPPINGS: ValueMappings = {}

export type CreateNameError = 'required' | 'exists'

/** Why a "create new status" entry cannot be sent: blank name, or a name an existing option already has. */
export function createNameError(field: ValueMappingField, entry: ValueMappingEntry | undefined): CreateNameError | null {
  if (!entry || !('create' in entry)) return null
  const name = normalizeText(entry.create.name)
  if (name === '') return 'required'
  return field.options.some((option) => normalizeText(option.name) === name) ? 'exists' : null
}

/** Whether an entry can be sent: an id, or a create with a non-empty name. */
export function isCompleteEntry(entry: ValueMappingEntry | undefined): entry is ValueMappingEntry {
  if (!entry) return false
  return 'id' in entry || entry.create.name.trim().length > 0
}

/** Looks a saved `{id}` entry up by the file value, ignoring case/accents/punctuation. */
function templateEntry(
  field: ValueMappingField,
  value: string,
  template: ValueMappings | null | undefined,
): ValueMappingEntry | undefined {
  const saved = template?.[field.field]
  if (!saved) return undefined
  const wanted = normalizeText(value)
  for (const [savedValue, entry] of Object.entries(saved)) {
    if (normalizeText(savedValue) !== wanted || !('id' in entry)) continue
    return field.options.some((option) => option.id === entry.id) ? entry : undefined
  }
  return undefined
}

/**
 * The entry a file value ends up with: the operator's own choice wins, then
 * the saved template (pre-applied), then the server suggestion.
 */
export function effectiveEntry(
  field: ValueMappingField,
  value: ValueMappingField['values'][number],
  manual: ValueMappings,
  template: ValueMappings | null | undefined,
): ValueMappingEntry | undefined {
  const own = manual[field.field]?.[value.value]
  if (own) return own
  const saved = templateEntry(field, value.value, template)
  if (saved) return saved
  return value.suggested_id != null ? { id: value.suggested_id } : undefined
}

export interface ResolvedValueMappings {
  /** Complete entries per field, ready for the `configure` payload. */
  payload: ValueMappings
  /** Distinct values still without a (complete) match. */
  unmatched: number
}

export function resolveValueMappings(
  fields: ValueMappingField[],
  manual: ValueMappings,
  template: ValueMappings | null | undefined,
): ResolvedValueMappings {
  const payload: ValueMappings = {}
  let unmatched = 0
  for (const field of fields) {
    const entries: Record<string, ValueMappingEntry> = {}
    for (const value of field.values) {
      const entry = effectiveEntry(field, value, manual, template)
      if (isCompleteEntry(entry) && createNameError(field, entry) === null) {
        entries[value.value] = entry
      } else {
        unmatched += 1
      }
    }
    payload[field.field] = entries
  }
  return { payload, unmatched }
}
