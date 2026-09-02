import { useEffect } from 'react'
import type { BlockedSection } from '@/features/personal-data/personal-data-issues'

/**
 * Brings the anagraphic block that refused the save into view. The owner forms
 * split the tree across tabs (the card on one, contacts/addresses on another),
 * and a field highlighted inside a hidden `TabsContent` is not rendered at all
 * — the user would read "fill the first name" next to a tab that shows none.
 * Every tabbed owner form runs this so the refused block is the one on screen.
 */
export function useRevealBlockedSection(
  signal: number,
  section: BlockedSection | null,
  tabs: Record<BlockedSection, string>,
  setActiveTab: (tab: string) => void,
): void {
  useEffect(() => {
    if (signal > 0 && section) {
      setActiveTab(tabs[section])
    }
    // Only a NEW refusal reveals: `tabs` is an inline literal and `section`
    // keeps its value while the user fixes the field, so re-running on either
    // would fight the user's own tab clicks.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [signal])
}
