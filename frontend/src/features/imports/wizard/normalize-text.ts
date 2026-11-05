/**
 * Lowercase, accent-free, punctuation-free form of a label, used to compare a
 * file header or cell with a catalog name (spec 0348 attribute suggestion and
 * saved value-mapping lookup).
 */
export function normalizeText(value: string): string {
  return value
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, ' ')
    .trim()
}
