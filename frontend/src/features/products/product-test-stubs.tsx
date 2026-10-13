/** Picker stand-ins of the product suites (spec 0218). */

interface StubSearchableSelectProps {
  value: number | null
  onChange: (id: number) => void
  disabled?: boolean
}

/** `SearchableSelect` stand-in for the category picker: a button picking category 3. */
export function StubSearchableSelect({ value, onChange, disabled }: StubSearchableSelectProps) {
  return (
    <button type="button" disabled={disabled} data-testid="category-select" onClick={() => onChange(3)}>
      {value ?? ''}
    </button>
  )
}

interface StubAsyncSelectProps {
  value: number | null
  onChange: (value: number | null) => void
  labels: { triggerLabel: string }
}

/** `AsyncPaginatedSelect` stand-in: shows the value, a button picks id 7. */
export function StubAsyncSelect({ value, onChange, labels }: StubAsyncSelectProps) {
  return (
    <button type="button" data-testid={`select-${labels.triggerLabel}`} onClick={() => onChange(7)}>
      {value ?? ''}
    </button>
  )
}
