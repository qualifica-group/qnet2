import type { HelpGuide } from '@/features/help/types'

/** Minimal, deterministic fake guide content for tests — never the real authored copy. */
export function buildFakeHelpGuide(key: string, overrides: Partial<HelpGuide> = {}): HelpGuide {
  return {
    key,
    title: `Title ${key}`,
    summary: `Summary ${key}`,
    sections: [
      {
        id: 'overview',
        title: `Overview ${key}`,
        blocks: [{ type: 'paragraph', text: `Body ${key}` }],
      },
    ],
    ...overrides,
  }
}
