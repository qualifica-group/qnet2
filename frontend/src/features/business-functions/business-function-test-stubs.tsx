/*
 * Controllable stand-ins for the network-backed pickers (responsabile and
 * parent function share the single-select, users and operational sites the
 * multi-select), keyed by `resource` so each instance stays addressable. The
 * business-function suites test the form's own logic, not the pickers
 * (covered by their component tests).
 */

export function AsyncPaginatedSelectStub({
  resource,
  value,
  onChange,
}: {
  resource: string
  value: number | null
  onChange: (value: number | null) => void
}) {
  const pickedId = resource === 'users' ? 5 : 50
  return (
    <div>
      <span data-testid={`${resource}-value`}>{value ?? ''}</span>
      <button type="button" onClick={() => onChange(pickedId)}>
        {`select-${resource}-${pickedId}`}
      </button>
      <button type="button" onClick={() => onChange(null)}>
        {`clear-${resource}`}
      </button>
    </div>
  )
}

export function AsyncPaginatedMultiSelectStub({
  resource,
  value,
  onChange,
}: {
  resource: string
  value: number[]
  onChange: (value: number[]) => void
}) {
  const addedId = resource === 'users' ? 7 : 70
  return (
    <div>
      <span data-testid={`${resource}-values`}>{value.join(',')}</span>
      <button type="button" onClick={() => onChange([...value, addedId])}>
        {`add-${resource}-${addedId}`}
      </button>
    </div>
  )
}
