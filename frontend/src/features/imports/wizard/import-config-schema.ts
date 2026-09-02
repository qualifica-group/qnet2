import type { ImportGlobalFieldDescriptor } from '@/features/imports/wizard/types'

/**
 * Every global config field is held as `number | number[] | null`: a
 * relation id (or unset) for a single-value field, an id array for a
 * `multiple` one (spec 0094, e.g. `product_ids`). Keeping the value type
 * uniform lets the form start from an explicit default per field, with
 * "required" enforced by the mapping schema's refinement (the config
 * controls live inside the mapping step's form — see `import-step-mapping.tsx`).
 */
export type ImportConfigFormValues = Record<string, number | number[] | null>

/**
 * Fills every known field id with an explicit default — `null` for a
 * single-value field, `[]` for a `multiple` one — so an incomplete caller
 * value never leaves a key `undefined` in the form's `global_config` object.
 */
export function withConfigDefaults(
  globalFields: ImportGlobalFieldDescriptor[],
  values: ImportConfigFormValues,
): ImportConfigFormValues {
  const filled: ImportConfigFormValues = {}
  for (const field of globalFields) {
    const fallback = field.multiple ? [] : null
    filled[field.id] = values[field.id] ?? fallback
  }
  return filled
}
