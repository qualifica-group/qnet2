import { describe, expect, it } from 'vitest'
import { HELP_GUIDE_KEYS } from '@/features/help/help-guide-keys'
import type { HelpGuide } from '@/features/help/types'

/**
 * Spec 0143, AC-007/AC-009: every one of the 50 keys needs an authored
 * `content/it/<key>.ts` and `content/en/<key>.ts`, both exporting a
 * `HelpGuide` whose `key` matches the filename and whose section ids form
 * the SAME sequence in both locales. `work-orders` additionally keeps its
 * "modulo in fase di sviluppo" section as a single note (D-2 of the
 * spec's context).
 *
 * Content authoring is owned by other teammates writing directly into
 * `content/it/` and `content/en/` — this file only reads what is there via
 * an eager glob, it never writes into those directories.
 */
const itModules = import.meta.glob<{ default: HelpGuide }>('./it/*.ts', { eager: true })
const enModules = import.meta.glob<{ default: HelpGuide }>('./en/*.ts', { eager: true })

const KEBAB_CASE_PATTERN = /^[a-z0-9]+(-[a-z0-9]+)*$/

function guideFor(
  modules: Record<string, { default: HelpGuide }>,
  locale: 'it' | 'en',
  key: string,
): HelpGuide | undefined {
  return modules[`./${locale}/${key}.ts`]?.default
}

describe('help content parity (spec 0143 AC-007)', () => {
  it.each(HELP_GUIDE_KEYS)('key "%s" has a matching IT and EN guide', (key) => {
    const itGuide = guideFor(itModules, 'it', key)
    const enGuide = guideFor(enModules, 'en', key)

    expect(itGuide, `content/it/${key}.ts is missing or has no default export`).toBeDefined()
    expect(enGuide, `content/en/${key}.ts is missing or has no default export`).toBeDefined()
    if (!itGuide || !enGuide) {
      return
    }

    expect(itGuide.key).toBe(key)
    expect(enGuide.key).toBe(key)

    const itSectionIds = itGuide.sections.map((section) => section.id)
    const enSectionIds = enGuide.sections.map((section) => section.id)
    expect(itSectionIds, `"${key}" must have the same section id sequence in IT and EN`).toEqual(
      enSectionIds,
    )

    for (const sectionId of itSectionIds) {
      expect(sectionId, `section id "${sectionId}" of "${key}" must be kebab-case`).toMatch(
        KEBAB_CASE_PATTERN,
      )
    }
  })

  it('work-orders (AC-009) keeps its "in-development" section as a single "note" block in both locales', () => {
    const itGuide = guideFor(itModules, 'it', 'work-orders')
    const enGuide = guideFor(enModules, 'en', 'work-orders')

    expect(itGuide, 'content/it/work-orders.ts is missing').toBeDefined()
    expect(enGuide, 'content/en/work-orders.ts is missing').toBeDefined()
    if (!itGuide || !enGuide) {
      return
    }

    // Spec 0190 added the "costs" section next to the placeholder: the module is still in development.
    for (const guide of [itGuide, enGuide]) {
      const placeholder = guide.sections.find((section) => section.id === 'in-development')
      expect(placeholder?.blocks).toHaveLength(1)
      expect(placeholder?.blocks[0]?.type).toBe('note')
    }
  })
})
