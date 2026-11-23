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

/**
 * The secondary referent after the Referenti set or the main referent changed
 * (spec 0408 D-4, D-9): mirrors the server (INV-2). Kept while still listed and
 * not the main referent; when empty, the first referent just added to the list
 * that is not the main one; otherwise none.
 */
export function nextSecondaryReferentId(
  current: number | null,
  primaryId: number | null,
  referentIds: number[],
  previousReferentIds: number[],
): number | null {
  if (current !== null && current !== primaryId && referentIds.includes(current)) {
    return current
  }
  return referentIds.find((id) => id !== primaryId && !previousReferentIds.includes(id)) ?? null
}
