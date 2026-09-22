/**
 * Content schema of the in-app module guides (spec 0143, frozen contract).
 * One guide per navigation key with a route, plus the always-visible
 * 'general' guide. Guides live in `content/{it,en}/<key>.ts` as a default
 * export and are rendered as React nodes only: plain text, where the single
 * inline markup allowed is `**bold**`.
 */
export type HelpGuideKey = string

export type HelpBlock =
  | { type: 'paragraph'; text: string }
  | { type: 'steps'; items: string[] }
  | { type: 'list'; items: string[] }
  | { type: 'table'; headers: string[]; rows: string[][] }
  | { type: 'tip'; text: string }
  | { type: 'warning'; text: string }
  | { type: 'note'; text: string }

export interface HelpSection {
  /** Stable kebab-case English id, identical across locales. */
  id: string
  title: string
  blocks: HelpBlock[]
}

export interface HelpGuide {
  key: HelpGuideKey
  title: string
  summary: string
  sections: HelpSection[]
}
