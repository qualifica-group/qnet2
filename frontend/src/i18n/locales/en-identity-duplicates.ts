/**
 * Live duplicate panel shared by the anagrafica and referente create forms
 * (spec 0037, extended by the user directive 2026-09-09 to CF, P.IVA and the
 * whole identity namespace). Lives in its own file, not under `referents`,
 * because both modules render the same component.
 */
export const identityDuplicates = {
  title: 'Possible duplicate',
  entry: '{{owner}} {{name}} might be a duplicate ({{criteria}}).',
  owners: {
    user: 'User',
    registry: 'Anagrafica',
    referent: 'Referent',
  },
  criteria: {
    email: 'email',
    phone: 'phone',
    mobile: 'mobile',
    taxCode: 'tax code',
    vatNumber: 'VAT number',
  },
}
