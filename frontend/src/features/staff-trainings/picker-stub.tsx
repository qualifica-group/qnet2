/** Stand-in for the for-select picker: a native select so the tests pick by accessible name. */
export function PickerStub({
  value,
  onChange,
  labels,
  ...rest
}: {
  value: number | null
  onChange: (value: number | null) => void
  labels: { triggerLabel: string }
  id?: string
  'aria-describedby'?: string
  'aria-invalid'?: boolean
}) {
  return (
    <select
      id={rest.id}
      aria-describedby={rest['aria-describedby']}
      aria-invalid={rest['aria-invalid']}
      aria-label={labels.triggerLabel}
      value={value === null ? '' : String(value)}
      onChange={(event) => onChange(event.target.value === '' ? null : Number(event.target.value))}
    >
      <option value="">-</option>
      <option value="3">Opzione 3</option>
      <option value="7">Opzione 7</option>
    </select>
  )
}
