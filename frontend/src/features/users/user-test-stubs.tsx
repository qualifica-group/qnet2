/** Picker stand-ins of the user suites (spec 0222). */

interface StubAsyncSelectProps {
  resource: string
  value: number | null
  onChange: (value: number | null) => void
  selectedItem?: { id: number; label: string } | null
  labels: { triggerLabel: string }
}

/**
 * `AsyncPaginatedSelect` stand-in, keyed by its trigger label: shows the bound
 * resource, value and hydrated label, picks id 1 or one of the competence
 * functions (4, 5), and clears.
 */
export function StubAsyncSelect({ resource, value, onChange, selectedItem, labels }: StubAsyncSelectProps) {
  const label = labels.triggerLabel
  return (
    <div>
      <span data-testid={`resource-${label}`}>{resource}</span>
      <output aria-label={`${label} value`}>{value ?? ''}</output>
      <span data-testid={`selected-label-${label}`}>{selectedItem?.label ?? ''}</span>
      <button type="button" onClick={() => onChange(1)}>{`pick-${label}`}</button>
      {[4, 5].map((id) => (
        <button key={id} type="button" onClick={() => onChange(id)}>{`select ${label} ${id}`}</button>
      ))}
      <button type="button" onClick={() => onChange(null)}>{`clear-${label}`}</button>
    </div>
  )
}

interface StubMultiSelectProps {
  resource: string
  value: number[]
  onChange: (value: number[]) => void
  selectedItems?: { id: number; label: string }[]
  labels: { triggerLabel: string }
}

/** `AsyncPaginatedMultiSelect` stand-in: shows the resource and hydrated labels, appends id 1, clears. */
export function StubMultiSelect({ resource, value, onChange, selectedItems, labels }: StubMultiSelectProps) {
  const label = labels.triggerLabel
  return (
    <div>
      <span data-testid={`resource-${label}`}>{resource}</span>
      <span data-testid={`selected-label-${label}`}>{(selectedItems ?? []).map((item) => item.label).join(', ')}</span>
      <button type="button" onClick={() => onChange([...value, 1])}>{`pick-${label}`}</button>
      <button type="button" onClick={() => onChange([])}>{`clear-${label}`}</button>
    </div>
  )
}
