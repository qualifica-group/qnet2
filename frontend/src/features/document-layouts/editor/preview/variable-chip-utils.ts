import type { DocumentLayoutVariablesCatalog } from '@/features/document-layouts/variables-api'

/** Matches a `{category.key}` reference (lowercase snake_case, spec 0069 `variables` endpoint syntax). */
const VARIABLE_PATTERN = /\{[a-z][a-z0-9_]*\.[a-z0-9_]+\}/g

export interface TextSegment {
  kind: 'text'
  value: string
}
export interface ChipSegment {
  kind: 'chip'
  variable: string
  label: string
}
export type RenderedSegment = TextSegment | ChipSegment

/** Splits run text into plain-text and variable-chip segments (AC-127: tokens render as chips, not raw text). */
export function splitVariableSegments(text: string, labelFor: (variable: string) => string): RenderedSegment[] {
  const segments: RenderedSegment[] = []
  let lastIndex = 0
  for (const match of text.matchAll(VARIABLE_PATTERN)) {
    const index = match.index ?? 0
    if (index > lastIndex) {
      segments.push({ kind: 'text', value: text.slice(lastIndex, index) })
    }
    segments.push({ kind: 'chip', variable: match[0], label: labelFor(match[0]) })
    lastIndex = index + match[0].length
  }
  if (lastIndex < text.length) {
    segments.push({ kind: 'text', value: text.slice(lastIndex) })
  }
  return segments
}

/** Builds a `variable -> label` lookup from the loaded catalog; falls back to the raw token when absent/loading. */
export function buildVariableLabelLookup(catalog: DocumentLayoutVariablesCatalog | undefined): (variable: string) => string {
  const map = new Map<string, string>()
  for (const category of catalog?.categories ?? []) {
    for (const variable of category.variables) {
      map.set(variable.variable, variable.label)
    }
  }
  return (variable: string) => map.get(variable) ?? variable
}
