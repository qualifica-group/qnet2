/** Picker stand-ins of the project suites (spec 0220). */

interface StubAsyncSelectProps {
  value: number | null
  onChange: (value: number) => void
  disabled?: boolean
  labels: { triggerLabel: string }
}

/** `AsyncPaginatedSelect` stand-in, keyed by its trigger label: picks id 3, shows the value. */
export function StubAsyncSelect({ value, onChange, disabled, labels }: StubAsyncSelectProps) {
  return (
    <button type="button" disabled={disabled} data-testid={`select-${labels.triggerLabel}`} onClick={() => onChange(3)}>
      {value ?? ''}
    </button>
  )
}

interface StubTreeSelectProps {
  value: number | null
  onChange: (id: number) => void
  scope: { kind: 'root'; rootCategoryId: number | null }
  disabled?: boolean
  triggerLabel: string
}

/** `ProductCategoryTreeSelect` stand-in: disabled until its row's root is chosen, picks category 4. */
export function StubTreeSelect({ value, onChange, scope, disabled, triggerLabel }: StubTreeSelectProps) {
  const isDisabled = Boolean(disabled) || scope.rootCategoryId === null
  return (
    <div>
      <span data-testid={`value-${triggerLabel}`}>{value ?? ''}</span>
      <span data-testid={`disabled-${triggerLabel}`}>{String(isDisabled)}</span>
      <button type="button" disabled={isDisabled} data-testid={`select-${triggerLabel}`} onClick={() => onChange(4)}>
        {`select ${triggerLabel}`}
      </button>
    </div>
  )
}

interface StubRootSelectProps {
  value: number | null
  onChange: (rootCategoryId: number) => void
  disabled?: boolean
  triggerLabel: string
}

/** `ProductCategoryRootSelect` stand-in: picks root 3. */
export function StubRootSelect({ value, onChange, disabled, triggerLabel }: StubRootSelectProps) {
  return (
    <button type="button" disabled={disabled} data-testid={`select-${triggerLabel}`} onClick={() => onChange(3)}>
      {value ?? ''}
    </button>
  )
}

interface GeoValue {
  country_id: number | null
  state_id: number | null
  province_id: number | null
  city_id: number | null
}

/** `GeoSelect` stand-in: picks country 1 alone. */
export function StubGeoSelect({ value, onChange }: { value: GeoValue; onChange: (value: GeoValue) => void }) {
  return (
    <button
      type="button"
      data-testid="geo-select"
      onClick={() => onChange({ country_id: 1, state_id: null, province_id: null, city_id: null })}
    >
      {value.country_id ?? ''}
    </button>
  )
}
