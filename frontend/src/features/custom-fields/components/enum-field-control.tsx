import { MultiSelect } from '@/components/ui/multi-select'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { EnumBadgePicker } from '@/features/custom-fields/components/enum-badge-picker'
import { EnumChecklist } from '@/features/custom-fields/components/enum-checklist'
import { EnumRadioGroup } from '@/features/custom-fields/components/enum-radio-group'
import type { CustomFieldControlProps } from '@/features/custom-fields/components/custom-field-control-props'

/** Keeps only the entries that are actually strings (defensive against a malformed stored value). */
function toStringArray(value: unknown): string[] {
  return Array.isArray(value) ? value.filter((entry): entry is string => typeof entry === 'string') : []
}

/**
 * `type: 'enum'` → `Select` (single, default), `MultiSelect`, `EnumChecklist`,
 * `EnumRadioGroup` or `EnumBadgePicker` per `config.display`. Options always come from
 * `descriptor.options` (never `form_enums`: custom enums are admin-defined
 * per field, not a shared app-wide enum).
 */
export function EnumFieldControl({
  descriptor,
  value,
  onChange,
  disabled,
  id,
  describedBy,
  invalid,
}: CustomFieldControlProps) {
  const options = descriptor.options ?? []
  const display = descriptor.config?.display ?? 'select'

  if (display === 'multiselect') {
    // MultiSelect (components/ui/) does not accept id/aria-describedby: the
    // accessible name falls back to `aria-label` only (documented in
    // CustomFieldControlProps).
    return (
      <MultiSelect
        options={options.map((option) => ({ value: option.value, label: option.label }))}
        value={toStringArray(value)}
        onChange={onChange}
        disabled={disabled}
        aria-label={descriptor.label}
      />
    )
  }

  if (display === 'checklist') {
    return (
      <EnumChecklist
        id={id}
        describedBy={describedBy}
        invalid={invalid}
        label={descriptor.label}
        options={options}
        value={toStringArray(value)}
        onChange={onChange}
        disabled={disabled}
      />
    )
  }

  const singleValue = typeof value === 'string' ? value : null

  if (display === 'radio') {
    return (
      <EnumRadioGroup
        id={id}
        describedBy={describedBy}
        invalid={invalid}
        label={descriptor.label}
        options={options}
        value={singleValue}
        onChange={onChange}
        disabled={disabled}
      />
    )
  }

  if (display === 'badge') {
    return (
      <EnumBadgePicker
        id={id}
        describedBy={describedBy}
        invalid={invalid}
        label={descriptor.label}
        options={options}
        value={singleValue}
        onChange={onChange}
        disabled={disabled}
      />
    )
  }

  return (
    <Select value={singleValue ?? undefined} onValueChange={onChange} disabled={disabled}>
      <SelectTrigger
        id={id}
        aria-label={descriptor.label}
        aria-describedby={describedBy}
        aria-invalid={invalid}
        className="w-full"
      >
        <SelectValue placeholder={descriptor.placeholder ?? undefined} />
      </SelectTrigger>
      <SelectContent>
        {options.map((option) => (
          <SelectItem key={option.value} value={option.value}>
            {option.label}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
