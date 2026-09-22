import type { HelpBlock, HelpGuide, HelpSection } from '@/features/help/types'
import { normalizeHelpSearchText } from '@/features/help/help-text-normalize'

export interface HelpSearchResult {
  guideKey: string
  guideTitle: string
  sectionId: string
  sectionTitle: string
}

function blockMatches(block: HelpBlock, normalizedQuery: string): boolean {
  switch (block.type) {
    case 'paragraph':
    case 'tip':
    case 'warning':
    case 'note':
      return normalizeHelpSearchText(block.text).includes(normalizedQuery)
    case 'steps':
    case 'list':
      return block.items.some((item) => normalizeHelpSearchText(item).includes(normalizedQuery))
    case 'table':
      return (
        block.headers.some((header) => normalizeHelpSearchText(header).includes(normalizedQuery)) ||
        block.rows.some((row) =>
          row.some((cell) => normalizeHelpSearchText(cell).includes(normalizedQuery)),
        )
      )
  }
}

function sectionMatches(section: HelpSection, normalizedQuery: string): boolean {
  if (normalizeHelpSearchText(section.title).includes(normalizedQuery)) {
    return true
  }
  return section.blocks.some((block) => blockMatches(block, normalizedQuery))
}

/**
 * Matches a query against title, section titles and block text/items/cells of
 * each loaded guide (AC-005). A guide-title-only match (no section content
 * involved) still needs a section to scroll to: it falls back to the first
 * section so the result stays navigable.
 */
export function searchHelpGuides(guides: HelpGuide[], query: string): HelpSearchResult[] {
  const normalizedQuery = normalizeHelpSearchText(query)
  const results: HelpSearchResult[] = []

  for (const guide of guides) {
    const titleMatches = normalizeHelpSearchText(guide.title).includes(normalizedQuery)
    let matchedSection = false

    for (const section of guide.sections) {
      if (sectionMatches(section, normalizedQuery)) {
        matchedSection = true
        results.push({
          guideKey: guide.key,
          guideTitle: guide.title,
          sectionId: section.id,
          sectionTitle: section.title,
        })
      }
    }

    const firstSection = guide.sections[0]
    if (titleMatches && !matchedSection && firstSection) {
      results.push({
        guideKey: guide.key,
        guideTitle: guide.title,
        sectionId: firstSection.id,
        sectionTitle: firstSection.title,
      })
    }
  }

  return results
}
