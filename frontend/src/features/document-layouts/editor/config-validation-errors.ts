import { DOCUMENT_LAYOUT_ZONES } from '@/features/document-layouts/layout-config'
import type { DocumentLayoutZoneName } from '@/features/document-layouts/layout-config'

/** One 422 error from `DocumentLayoutConfigValidator`, mapped from its dotted path (AC-129). */
export interface ConfigValidationError {
  /** The zone the error belongs to, when the path points inside `<zone>.blocks.<index>`. */
  zone: DocumentLayoutZoneName | null
  /** The zero-based block index inside that zone, when resolvable. */
  blockIndex: number | null
  /** Remaining path segments after `config.<zone>.blocks.<index>`, e.g. `['runs', '0', 'size']`. */
  path: string[]
  /** The full server-side dotted path, e.g. `config.body.blocks.3.runs.0.size`. */
  rawPath: string
  message: string
}

function isZoneName(segment: string): segment is DocumentLayoutZoneName {
  return (DOCUMENT_LAYOUT_ZONES as readonly string[]).includes(segment)
}

/**
 * Parses every `config.*` key of a 422 `errors` payload into a
 * `ConfigValidationError`, so the editor attributes it to the exact
 * offending block instead of a generic form-wide message (AC-129: a path
 * like `config.body.blocks.3.runs.0.size` must reach the block at index 3 of
 * the `body` zone, not a flat form error).
 */
export function parseConfigValidationErrors(errors: Record<string, string[]> | undefined): ConfigValidationError[] {
  if (!errors) {
    return []
  }

  const parsed: ConfigValidationError[] = []
  for (const [rawPath, messages] of Object.entries(errors)) {
    const segments = rawPath.split('.')
    const message = messages[0]
    if (segments[0] !== 'config' || !message) {
      continue
    }

    const zoneSegment = segments[1]
    const isBlockPath = zoneSegment !== undefined && isZoneName(zoneSegment) && segments[2] === 'blocks'
    const blockIndex = isBlockPath ? Number(segments[3]) : NaN

    parsed.push({
      zone: isBlockPath ? (zoneSegment as DocumentLayoutZoneName) : null,
      blockIndex: isBlockPath && Number.isInteger(blockIndex) ? blockIndex : null,
      path: isBlockPath ? segments.slice(4) : segments.slice(1),
      rawPath,
      message,
    })
  }
  return parsed
}
