import axios from 'axios'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/**
 * The form's map over the stored one. A key the form holds as `undefined` is
 * a field that registered before its seed (a new product's row mounting), not
 * a value: it must not hide the stored one.
 */
export function withStoredFallback(
  current: Record<string, CustomFieldValue | undefined>,
  stored: Record<string, CustomFieldValue>,
): Record<string, CustomFieldValue> {
  const merged = { ...stored }
  for (const [code, value] of Object.entries(current)) {
    if (value !== undefined) {
      merged[code] = value
    }
  }
  return merged
}

/**
 * Spec 0087 (D-6/AC-004): the create/update 422 is the "not a G.A. of the
 * Opportunity yet" refusal when the error bag carries a `manager_slots` (or
 * `manager_slots.<n>`) key — the client never rejects it earlier (MAX is the
 * only client-side rule), so a 422 landing there past that point is this
 * violation. Returns the server's own message (AC-004 names the user) or
 * `null` when the failure is unrelated.
 */
export function managerSlotsMembershipMessage(error: unknown): string | null {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return null
  }
  const errors = error.response.data?.errors as Record<string, string[]> | undefined
  const key = Object.keys(errors ?? {}).find(
    (field) => field === 'manager_slots' || field.startsWith('manager_slots.'),
  )
  return key ? (errors as Record<string, string[]>)[key][0] : null
}
