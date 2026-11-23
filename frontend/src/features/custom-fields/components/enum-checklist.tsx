import { useId } from 'react'
import { Checkbox } from '@/components/ui/checkbox'
import { cn } from '@/lib/utils'
import type { CustomFieldOption } from '@/features/custom-fields/types'

interface EnumChecklistProps {
  id: string
  describedBy: string
  invalid: boolean
  label: string
  options: CustomFieldOption[]
  value: string[]
  onChange: (value: string[]) => void
  disabled: boolean
}

/**
 * `enum` field, `config.display: 'checklist'` (spec 0424 D-1): one checkbox
 * per option, any number ticked. The value is the list of the ticked option
 * values, kept in the options' order. The boxes flow in as many columns as
 * the field's width holds (no breakpoint: the same field sits in a full-width
 * form row or in a narrow half-width cell), one column on a phone.
 */
export function EnumChecklist({
  id,
  describedBy,
  invalid,
  label,
  options,
  value,
  onChange,
  disabled,
}: EnumChecklistProps) {
  const baseId = useId()

  const toggle = (optionValue: string, checked: boolean) => {
    const ticked = new Set(value)
    if (checked) {
      ticked.add(optionValue)
    } else {
      ticked.delete(optionValue)
    }
    const known = options.map((option) => option.value).filter((entry) => ticked.has(entry))
    onChange([...known, ...value.filter((entry) => ticked.has(entry) && !known.includes(entry))])
  }

  return (
    <div
      id={id}
      role="group"
      aria-label={label}
      aria-describedby={describedBy}
      aria-invalid={invalid}
      className="grid grid-cols-[repeat(auto-fill,minmax(8.5rem,1fr))] gap-x-4 gap-y-2"
    >
      {options.map((option, index) => {
        const checkboxId = `${baseId}-${index}`
        return (
          <div key={option.value} className="flex min-w-0 items-start gap-2">
            <Checkbox
              id={checkboxId}
              checked={value.includes(option.value)}
              disabled={disabled}
              onCheckedChange={(next) => toggle(option.value, next === true)}
            />
            <label
              htmlFor={checkboxId}
              className={cn('text-sm leading-4', disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer')}
            >
              {option.label}
            </label>
          </div>
        )
      })}
    </div>
  )
}
