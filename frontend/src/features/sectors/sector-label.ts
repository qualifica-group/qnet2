/** Separator between the code and the name, matching the backend `Sector::selectLabel()`. */
const LABEL_SEPARATOR = ' — '

/**
 * The label every sector picker shows: "{code} — {name}", or the bare name
 * when the sector has no code (spec 0213). Mirrors the backend label of the
 * for-select endpoint, for the options built client-side (tree, quick-create).
 */
export function sectorLabel({ code, name }: { code: string | null; name: string }): string {
  const trimmed = code?.trim() ?? ''
  return trimmed === '' ? name : `${trimmed}${LABEL_SEPARATOR}${name}`
}
