/**
 * Shared helpers for the contacts quick-create flow, kept out of
 * `contacts-create-fields.tsx` so that component-only file can stay Fast
 * Refresh-friendly (only component exports).
 */
import type { ContactDraft } from '@/features/personal-data/types'

/** The contact types with a dedicated quick field (real `contact_type` enum values). */
export const QUICK_CONTACT_TYPES = ['email', 'phone', 'pec', 'fax', 'website'] as const
export type QuickContactType = (typeof QUICK_CONTACT_TYPES)[number]

/** The first buffered draft of `type` — the one a quick field owns. */
export function firstOfType(
  contacts: ContactDraft[],
  type: QuickContactType,
): ContactDraft | undefined {
  return contacts.find((contact) => contact.type === type)
}

/**
 * The `_key`s owned by the quick fields (the first draft of each quick type),
 * so the CRUD list can exclude them and the two surfaces never double-show a
 * row. Consumed by `ContactsManager`.
 */
export function quickOwnedKeys(contacts: ContactDraft[]): Set<string> {
  const keys = new Set<string>()
  for (const type of QUICK_CONTACT_TYPES) {
    const owned = firstOfType(contacts, type)
    if (owned) {
      keys.add(owned._key)
    }
  }
  return keys
}
