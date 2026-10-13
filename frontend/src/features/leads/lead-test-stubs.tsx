import type { ForSelectItem } from '@/features/for-select/types'
import {
  OPERATOR_NO_SITE,
  OPERATOR_WITH_SITE,
  PRODUCT_501,
  SITE_A,
  SITE_B,
  stubState,
} from '@/features/leads/lead-test-fixtures'

/** Picker stand-ins of the lead suites (spec 0217), driven by the fixtures' items and `stubState`. */

/** Cycles `items` from `value`: the next one after the current, the first when the current is not listed. */
function nextOf(items: ForSelectItem[], value: number | null): ForSelectItem {
  const index = items.findIndex((item) => item.id === value)
  return items[(index + 1) % items.length]
}

interface StubSelectProps {
  value: number | null
  onChange: (value: number | null) => void
  onItemChange?: (item: ForSelectItem | null) => void
  labels: { triggerLabel: string }
  params?: Record<string, unknown>
  disabled?: boolean
}

/**
 * `AsyncPaginatedSelect` stand-in, keyed by its accessible trigger label:
 * Campaign cycles `stubState.campaigns`, Site cycles SITE_A/SITE_B, Operator
 * cycles OPERATOR_WITH_SITE/OPERATOR_NO_SITE (each with its `meta`, so the
 * cascades run), anything else picks id 3. Exposes value, params and
 * disabled for assertions.
 */
export function StubSelect({ value, onChange, onItemChange, labels, params, disabled }: StubSelectProps) {
  const pick = () => {
    const label = labels.triggerLabel
    const pools: Record<string, ForSelectItem[]> = {
      Campaign: stubState.campaigns,
      Site: [SITE_A, SITE_B],
      Operator: [OPERATOR_WITH_SITE, OPERATOR_NO_SITE],
    }
    const pool = pools[label]
    const next = pool ? nextOf(pool, value) : null
    onChange(next ? next.id : 3)
    onItemChange?.(next)
  }

  return (
    <button
      type="button"
      data-testid={`select-${labels.triggerLabel}`}
      data-params={params ? JSON.stringify(params) : ''}
      disabled={disabled}
      onClick={pick}
    >
      {value ?? ''}
    </button>
  )
}

interface StubMultiSelectProps {
  value: number[]
  onChange: (value: number[]) => void
  labels: { triggerLabel: string }
  disabled?: boolean
  params?: Record<string, unknown>
}

/** `AsyncPaginatedMultiSelect` stand-in: exposes value/params/disabled and one button adding product 501. */
export function StubMultiSelect({ value, onChange, labels, disabled, params }: StubMultiSelectProps) {
  return (
    <div
      data-testid={`multi-${labels.triggerLabel}`}
      data-disabled={disabled ? 'true' : 'false'}
      data-params={JSON.stringify(params ?? {})}
    >
      <span data-testid="multi-value">{value.join(',')}</span>
      <button type="button" disabled={disabled} onClick={() => onChange([...value, PRODUCT_501.id])}>
        add product
      </button>
    </div>
  )
}
