/** Write-off / bad debt of the residual in the collection and payment dialogs (spec 0361). */
export const writeOff = {
  mode: 'Write-off / bad debt',
  amount: 'Write-off amount',
  reason: 'Reason',
  reasons: {
    rounding: 'Write-off',
    bad_debt: 'Bad debt',
  },
  note: 'Note (optional)',
  errors: {
    zeroNeedsWriteOff: 'A zero amount is only allowed with Write-off / bad debt.',
    noteTooLong: 'The note cannot exceed {{max}} characters.',
  },
}
