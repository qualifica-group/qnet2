import type { TFunction } from 'i18next'
import { MAX_MANAGER_SLOTS } from '@/components/form/manager-slots-limits'

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

/**
 * Every slot's visible label for `ManagerSlotsField`, the resolved overrides
 * over the shared default. A full map, never an empty one: it puts the editor
 * in its named layout (the label on its own line, the picker across the whole
 * row) the way Commesse already shows its Partecipanti, instead of the compact
 * number badge that squeezes the picker in the record's narrow value column.
 */
export function managerSlotLabels(
  t: TFunction,
  labels: Record<string, string> | undefined,
): Record<number, string> {
  return Object.fromEntries(
    Array.from({ length: MAX_MANAGER_SLOTS }, (_, index) => [index + 1, managerPositionLabel(t, index + 1, labels)]),
  )
}
