import type { TFunction } from 'i18next'

/**
 * The visible role label of a G.A. slot (spec 0080): the record's own resolved
 * override for that `position` when the product category configures one,
 * otherwise the same shared default `ManagerSlotsField` falls back to.
 *
 * VISIBLE text, never a `title`-only tooltip — a hover affordance is invisible
 * on touch and unreliable for screen readers.
 *
 * Shared between the Opportunity and Offerta detail panels (spec 0087): both
 * name the same slots, and two copies would be free to disagree on what a
 * level is called.
 */
export function managerPositionLabel(
  t: TFunction,
  position: number,
  labels: Record<string, string> | undefined,
): string {
  return labels?.[String(position)] ?? t('registries.form.managerSlotLabel', { n: position })
}
