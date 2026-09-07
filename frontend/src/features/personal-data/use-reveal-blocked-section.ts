import { useEffect, type RefObject } from 'react'
import type { BlockedSection } from '@/features/personal-data/personal-data-issues'

/**
 * Marks the wrapper of an anagraphic block, so a refused save can bring that
 * block into view. Spread on the element wrapping the block's `FormSection`.
 */
export function anagraphicSectionProps(section: BlockedSection): {
  'data-anagraphic-section': BlockedSection
} {
  return { 'data-anagraphic-section': section }
}

/**
 * Scrolls the anagraphic block that refused the save into view. The owner forms
 * lay the card, the contacts and the addresses out on ONE screen, so the block
 * is always mounted — but it can sit well above the save button the user just
 * pressed, and a banner naming a field nobody can see is no highlight at all.
 *
 * Scoped to the owner form's own container: a quick-create dialog mounting a
 * second owner form (a Segnalatore created from inside an Anagrafica) must
 * scroll ITS block, never the identically marked one of the form underneath.
 */
export function useRevealBlockedSection(
  signal: number,
  section: BlockedSection | null,
  containerRef: RefObject<HTMLElement | null>,
): void {
  useEffect(() => {
    if (signal === 0 || section === null) {
      return
    }
    containerRef.current
      ?.querySelector(`[data-anagraphic-section="${section}"]`)
      ?.scrollIntoView({ block: 'nearest' })
    // Only a NEW refusal reveals: `section` keeps its value while the user
    // fixes the field, so re-running on it would fight the user's own scrolling.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [signal])
}
