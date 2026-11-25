import type { FocusEvent } from 'react'
import { Input } from '@/components/ui/input'

interface CommissionInputProps {
  value: number
  /** Called on blur with the typed number, only when it changed. */
  onCommit: (value: number) => void
  label: string
  max?: number
  step: number
  placeholder: string
  disabled?: boolean
}

/**
 * A commission field saved when it loses focus, like the legacy
 * `wire:model.blur`. Uncontrolled: the caller keys it on the saved value, so
 * the server's answer (e.g. a clamped percentage) replaces what was typed.
 */
export function CommissionInput({ value, onCommit, label, max, step, placeholder, disabled }: CommissionInputProps) {
  const commit = (event: FocusEvent<HTMLInputElement>) => {
    const typed = event.currentTarget.value.trim() === '' ? 0 : Number(event.currentTarget.value)
    if (!Number.isNaN(typed) && typed !== value) {
      onCommit(typed)
    }
  }

  return (
    <Input
      type="number"
      inputMode="decimal"
      aria-label={label}
      defaultValue={value > 0 ? value : ''}
      min={0}
      max={max}
      step={step}
      placeholder={placeholder}
      disabled={disabled}
      onBlur={commit}
      className="h-7 w-20 bg-card px-1.5 text-xs"
    />
  )
}
