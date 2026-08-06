import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { ApplicableAttribute } from '@/features/request-management/types'

/**
 * Every applicable code with the record's stored value, or the type's "unset"
 * default when it has none: `false` for a `boolean` (user directive
 * 2026-07-31), `null` for every other type. A checkbox has no third state, so
 * an unfilled flag means "no", never "not answered".
 *
 * Load-bearing for TWO reasons, both of which produce silent failures:
 *
 * 1. The Zod object is built from the applicable codes with `z.object(shape)`,
 *    NOT a partial — a missing key fails validation, and `handleSubmit` aborts
 *    with errors on fields the operator never touched. Visible symptom: the
 *    Save button does nothing at all.
 * 2. It must be applied to the diff BASELINE too, not only to the defaults, or
 *    a freshly loaded record with no boolean stored reports itself as already
 *    modified and rewrites the whole map on any unrelated save.
 *
 * Lives here — under `features/attributes`, next to the renderer and the layout
 * types — rather than inside a host module: spec 0084 made it shared between
 * the Offerta form and Gestione Richieste, and a helper this easy to remove by
 * accident should not sit in a folder that another module is free to gut.
 */
/**
 * The stored map as a plain object, whatever the API sent.
 *
 * An empty PHP map serializes as a JSON ARRAY (`[]`, not `{}`), so a record
 * with no dynamic value stored hands the form an array where the Zod
 * `z.object(shape)` demands an object — `handleSubmit` then aborts with an
 * `attribute_values` error on no rendered field at all: the Save button does
 * nothing. `seedAttributeValues` cannot repair it afterwards, because with an
 * empty applicable set it produces `{}` and RHF's `setValue(name, {})` has no
 * key to recurse over: the array survives untouched.
 */
export function toAttributeValuesMap(stored: unknown): Record<string, CustomFieldValue> {
  if (stored === null || typeof stored !== 'object' || Array.isArray(stored)) {
    return {}
  }

  return stored as Record<string, CustomFieldValue>
}

export function seedAttributeValues(
  attributes: ApplicableAttribute[],
  values: Record<string, unknown>,
): Record<string, CustomFieldValue> {
  const seeded: Record<string, CustomFieldValue> = {}

  for (const attribute of attributes) {
    const stored = values[attribute.code] as CustomFieldValue | undefined
    seeded[attribute.code] = stored ?? (attribute.type === 'boolean' ? false : null)
  }

  return seeded
}
