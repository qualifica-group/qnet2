import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { cn } from '@/lib/utils'
import { useValidatedField } from '@/features/document-layouts/editor/shared/use-validated-field'

interface IntegerFieldProps {
  id?: string
  /** Visible label; pass `''` for a compact toolbar slot and supply `ariaLabel` instead. */
  label: string
  /** Accessible name used when `label` is visually hidden (empty). */
  ariaLabel?: string
  value: number
  min: number
  max: number
  step?: number
  onCommit: (value: number) => void
  disabled?: boolean
  className?: string
}

function parseBoundedInt(raw: string, min: number, max: number): number | null {
  const parsed = Number(raw)
  if (raw.trim() === '' || !Number.isInteger(parsed) || parsed < min || parsed > max) {
    return null
  }
  return parsed
}

/**
 * Compact bounded-integer input reused by every `width_pct`/margin/size/
 * height/thickness field of the editor (page margins, run/font size, image
 * dimensions, divider thickness…): out-of-range input shows an accessible
 * inline error and never reaches the committed config (AC-124).
 */
export function IntegerField({
  id,
  label,
  ariaLabel,
  value,
  min,
  max,
  step = 1,
  onCommit,
  disabled,
  className,
}: IntegerFieldProps) {
  const autoId = useId()
  const fieldId = id ?? autoId
  const errorId = `${fieldId}-error`
  const { t } = useTranslation()
  const { text, isInvalid, handleChange } = useValidatedField<number>(
    value,
    (current) => String(current),
    (raw) => parseBoundedInt(raw, min, max),
    onCommit,
  )

  return (
    <div className={cn('flex flex-col gap-1', className)}>
      {label && (
        <Label htmlFor={fieldId} className="text-xs text-muted-foreground">
          {label}
        </Label>
      )}
      <Input
        id={fieldId}
        type="number"
        inputMode="numeric"
        min={min}
        max={max}
        step={step}
        value={text}
        disabled={disabled}
        aria-label={label ? undefined : ariaLabel}
        aria-invalid={isInvalid}
        aria-describedby={isInvalid ? errorId : undefined}
        onChange={(event) => handleChange(event.target.value)}
        className="h-7 text-xs"
      />
      {isInvalid && (
        <span id={errorId} role="alert" className="text-xs text-destructive">
          {t('documentLayouts.editor.shared.integerOutOfRange', { min, max })}
        </span>
      )}
    </div>
  )
}
