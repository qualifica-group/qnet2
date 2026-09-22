import { describe, expect, it } from 'vitest'
import { loadHelpGuide, normalizeHelpLocale } from '@/features/help/help-content-loader'

describe('normalizeHelpLocale', () => {
  it('narrows a bare or region-tagged locale to its 2-letter code', () => {
    expect(normalizeHelpLocale('it')).toBe('it')
    expect(normalizeHelpLocale('it-IT')).toBe('it')
    expect(normalizeHelpLocale('en-US')).toBe('en')
  })

  it('falls back to the app fallback locale for anything unsupported', () => {
    expect(normalizeHelpLocale('fr')).toBe('en')
    expect(normalizeHelpLocale(undefined)).toBe('en')
    expect(normalizeHelpLocale(null)).toBe('en')
  })
})

describe('loadHelpGuide', () => {
  it('resolves null for a key with no authored content file (AC-012: nothing imported eagerly)', async () => {
    await expect(loadHelpGuide('it', 'this-key-has-no-content-file')).resolves.toBeNull()
  })
})
