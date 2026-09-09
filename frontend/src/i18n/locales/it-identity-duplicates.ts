/**
 * Live duplicate panel shared by the anagrafica and referente create forms
 * (spec 0037, extended by the user directive 2026-09-09 to CF, P.IVA and the
 * whole identity namespace). Lives in its own file, not under `referents`,
 * because both modules render the same component.
 */
export const identityDuplicates = {
  title: 'Possibile duplicato',
  entry: '{{owner}} {{name}} potrebbe essere un duplicato ({{criteria}}).',
  owners: {
    user: 'Utente',
    registry: 'Anagrafica',
    referent: 'Referente',
  },
  criteria: {
    email: 'email',
    phone: 'telefono',
    mobile: 'cellulare',
    taxCode: 'codice fiscale',
    vatNumber: 'partita IVA',
  },
}
