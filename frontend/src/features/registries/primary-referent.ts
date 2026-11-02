/**
 * The main referent after the Referenti set changed (spec 0330 D-9): mirrors
 * the server's election (D-4) so the form already shows what will be saved.
 * Kept while still listed; otherwise the first of the list, null when empty.
 */
export function nextPrimaryReferentId(current: number | null, referentIds: number[]): number | null {
  if (current !== null && referentIds.includes(current)) {
    return current
  }
  return referentIds[0] ?? null
}
