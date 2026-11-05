/** Abbuono / stralcio del residuo nelle finestre di incasso e pagamento (spec 0361). */
export const writeOff = {
  mode: 'Abbuono / stralcio',
  amount: 'Importo dell’abbuono',
  reason: 'Motivo',
  reasons: {
    rounding: 'Abbuono',
    bad_debt: 'Stralcio',
  },
  note: 'Nota (facoltativa)',
  errors: {
    zeroNeedsWriteOff: 'Un importo pari a zero è ammesso solo con Abbuono / stralcio.',
    noteTooLong: 'La nota non può superare {{max}} caratteri.',
  },
}
