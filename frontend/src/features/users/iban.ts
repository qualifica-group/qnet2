/** Groups an IBAN in blocks of four for reading (the stored value is uppercase, spaceless). */
export function formatIban(iban: string): string {
  return iban.replace(/\s+/g, '').replace(/(.{4})(?=.)/g, '$1 ')
}
