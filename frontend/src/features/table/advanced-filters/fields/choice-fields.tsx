import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { Checkbox } from '@/components/ui/checkbox'
import { MultiSelect } from '@/components/ui/multi-select'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { cn } from '@/lib/utils'
import { useEnumOptions } from '@/features/config/use-config'
import {
  resolveOptionValue,
  toOptionValueArray,
} from '@/features/table/advanced-filters/option-utils'
import type { AdvancedFilterFieldProps } from '@/features/table/advanced-filters/advanced-filter-field-props'

const NO_EXCLUDED_VALUES: string[] = []
/** `type: 'select'` -> a `Select` over the descriptor's static `options`. */
export function SelectAdvancedFilterField({
  descriptor,
  value,
  onChange,
  disabled,
  id,
  describedBy,
  invalid,
}: AdvancedFilterFieldProps) {
  const { t } = useTranslation()
  const options = descriptor.options ?? []
  const stringValue = value !== null && value !== undefined ? String(value) : undefined

  return (
    <Select
      value={stringValue}
      onValueChange={(next) => onChange(resolveOptionValue(options, next))}
      disabled={disabled}
    >
      <SelectTrigger
        id={id}
        aria-describedby={describedBy}
        aria-invalid={invalid}
        className="w-full"
      >
        <SelectValue
          placeholder={descriptor.placeholder ? t(descriptor.placeholder) : undefined}
        />
      </SelectTrigger>
      <SelectContent>
        {options.map((option) => (
          <SelectItem key={option.value} value={String(option.value)}>
            {option.label}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}

/**
 * `type: 'multiselect'` -> `MultiSelect` over the descriptor's static
 * `options`. `MultiSelect` does not accept id/aria-describedby: the
 * accessible name falls back to `aria-label` only (same documented tradeoff
 * as the custom-fields registry).
 */
export function MultiSelectAdvancedFilterField({
  descriptor,
  value,
  onChange,
  disabled,
}: AdvancedFilterFieldProps) {
  const { t } = useTranslation()
  const options = descriptor.options ?? []
  const stringOptions = options.map((option) => ({
    value: String(option.value),
    label: option.label,
  }))
  const selected = toOptionValueArray(value).map(String)

  return (
    <MultiSelect
      options={stringOptions}
      value={selected}
      onChange={(next) => onChange(next.map((raw) => resolveOptionValue(options, raw)))}
      disabled={disabled}
      placeholder={descriptor.placeholder ? t(descriptor.placeholder) : undefined}
      aria-label={t(descriptor.label)}
    />
  )
}

/** `type: 'enum'`, single-value -> a `Select` over `enums.<enumKey>` (app-wide domain enum, spec ADR 0008). */
function EnumSingleSelectField({
  descriptor,
  value,
  onChange,
  disabled,
  id,
  describedBy,
  invalid,
}: AdvancedFilterFieldProps) {
  const { t } = useTranslation()
  const options = useEnumOptions(descriptor.enumKey ?? '')
  const stringValue = typeof value === 'string' ? value : undefined

  return (
    <Select value={stringValue} onValueChange={onChange} disabled={disabled}>
      <SelectTrigger
        id={id}
        aria-describedby={describedBy}
        aria-invalid={invalid}
        className="w-full"
      >
        <SelectValue
          placeholder={descriptor.placeholder ? t(descriptor.placeholder) : undefined}
        />
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

/**
 * `type: 'enum'`, `multiple: true` (spec 0153 D-1/AC-019, e.g. Task's
 * `assignment` filter) -> `MultiSelect` over `enums.<enumKey>`, OR'd values.
 * `MultiSelect` does not accept id/aria-describedby: same documented
 * tradeoff as `MultiSelectAdvancedFilterField`.
 */
function EnumMultiSelectField({ descriptor, value, onChange, disabled }: AdvancedFilterFieldProps) {
  const { t } = useTranslation()
  const options = useEnumOptions(descriptor.enumKey ?? '')
  const excluded = descriptor.excludedValues ?? NO_EXCLUDED_VALUES
  const stringOptions = options
    .filter((option) => !excluded.includes(option.value))
    .map((option) => ({ value: option.value, label: option.label }))
  const selected = toOptionValueArray(value).map(String)

  return (
    <MultiSelect
      options={stringOptions}
      value={selected}
      onChange={onChange}
      disabled={disabled}
      placeholder={descriptor.placeholder ? t(descriptor.placeholder) : undefined}
      aria-label={t(descriptor.label)}
    />
  )
}

/** `type: 'enum'` -> dispatches on `descriptor.multiple` (spec 0153 AC-019). */
export function EnumAdvancedFilterField(props: AdvancedFilterFieldProps) {
  return props.descriptor.multiple ? <EnumMultiSelectField {...props} /> : <EnumSingleSelectField {...props} />
}

/**
 * `type: 'radio'` -> native `<input type="radio">` group over
 * `enums.<enumKey>`. There is no `radio-group.tsx` in `components/ui/`
 * (design-system ownership) to reuse — same tradeoff as the custom-fields
 * `EnumRadioGroup`.
 */
export function RadioAdvancedFilterField({
  descriptor,
  value,
  onChange,
  disabled,
  id,
  describedBy,
  invalid,
}: AdvancedFilterFieldProps) {
  const options = useEnumOptions(descriptor.enumKey ?? '')
  const groupName = useId()
  const stringValue = typeof value === 'string' ? value : null

  return (
    <div
      id={id}
      role="radiogroup"
      aria-describedby={describedBy}
      aria-invalid={invalid}
      className="flex flex-col gap-1.5"
    >
      {options.map((option) => (
        <label
          key={option.value}
          className={cn(
            'flex items-center gap-2 text-sm',
            disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
          )}
        >
          <input
            type="radio"
            name={groupName}
            value={option.value}
            checked={stringValue === option.value}
            disabled={disabled}
            onChange={() => onChange(option.value)}
            className="size-3.5"
          />
          {option.label}
        </label>
      ))}
    </div>
  )
}

/** `type: 'checkbox'` -> a `Checkbox`. */
export function CheckboxAdvancedFilterField({
  value,
  onChange,
  disabled,
  id,
  describedBy,
  invalid,
}: AdvancedFilterFieldProps) {
  return (
    <Checkbox
      id={id}
      checked={value === true}
      disabled={disabled}
      aria-describedby={describedBy}
      aria-invalid={invalid}
      onCheckedChange={(next) => onChange(next === true)}
    />
  )
}

/** `type: 'switch'` -> a `Switch`. */
export function SwitchAdvancedFilterField({
  value,
  onChange,
  disabled,
  id,
  describedBy,
  invalid,
}: AdvancedFilterFieldProps) {
  return (
    <Switch
      id={id}
      checked={value === true}
      disabled={disabled}
      aria-describedby={describedBy}
      aria-invalid={invalid}
      onCheckedChange={(next) => onChange(next === true)}
    />
  )
}
