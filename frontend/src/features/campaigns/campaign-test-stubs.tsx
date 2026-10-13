import { TEST_PROJECT_ID } from '@/features/campaigns/campaign-test-fixtures'

/** Picker stand-ins of the campaign suites (spec 0221). */

interface StubAsyncSelectProps {
  value: number | null
  onChange: (value: number | null) => void
  disabled?: boolean
  labels: { triggerLabel: string }
}

/**
 * `AsyncPaginatedSelect` stand-in, keyed by its trigger label: picks project
 * 42 on the Project picker and id 3 elsewhere, and offers a clear.
 */
export function StubAsyncSelect({ value, onChange, disabled, labels }: StubAsyncSelectProps) {
  const label = labels.triggerLabel
  return (
    <div>
      <button
        type="button"
        disabled={disabled}
        data-testid={`select-${label}`}
        onClick={() => onChange(label === 'Project' ? TEST_PROJECT_ID : 3)}
      >
        {value ?? ''}
      </button>
      <button type="button" disabled={disabled} data-testid={`clear-${label}`} onClick={() => onChange(null)}>
        {`clear ${label}`}
      </button>
    </div>
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
    <button type="button" disabled={isDisabled} data-testid={`select-${triggerLabel}`} onClick={() => onChange(4)}>
      {value ?? ''}
    </button>
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

interface StubGeoSelectProps {
  value: GeoValue
  onChange: (value: GeoValue) => void
  lockedLevels?: readonly string[]
}

/** `GeoSelect` stand-in: picks country 1 alone, exposes the locked levels (BR-5). */
export function StubGeoSelect({ value, onChange, lockedLevels }: StubGeoSelectProps) {
  return (
    <button
      type="button"
      data-testid="geo-select"
      data-locked={(lockedLevels ?? []).join(',')}
      onClick={() => onChange({ country_id: 1, state_id: null, province_id: null, city_id: null })}
    >
      {value.country_id ?? ''}
    </button>
  )
}
