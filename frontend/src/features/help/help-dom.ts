/** Stable DOM id of a rendered section, unique per guide (search results scroll to this). */
export function helpSectionDomId(guideKey: string, sectionId: string): string {
  return `help-section-${guideKey}-${sectionId}`
}
