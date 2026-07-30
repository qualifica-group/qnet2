import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useValidatedField } from '@/features/document-layouts/editor/shared/use-validated-field'

const HEX_COLOR_PATTERN = /^[0-9A-Fa-f]{6}$/

interface HexColorFieldProps {
  id?: string
  /** Visible label; pass `''` for a compact toolbar slot and supply `ariaLabel` instead. */
  label: string
  /** Accessible name used when `label` is visually hidden (empty). */
  ariaLabel?: string
  value: string
  onCommit: (value: string) => void
  disabled?: boolean
}

/**
 * Compact hex-color (`RRGGBB`, no `#`, OOXML convention) input with a swatch
 * preview, reused by every colored field of the editor (run color, divider,
 * table borders, header background, default page font). Invalid input shows
 * an accessible inline error and never reaches the committed config.
 */
export function HexColorField({ id, label, ariaLabel, value, onCommit, disabled }: HexColorFieldProps) {
  const autoId = useId()
  const fieldId = id ?? autoId
  const errorId = `${fieldId}-error`
  const { t } = useTranslation()
  const { text, isInvalid, handleChange } = useValidatedField<string>(
    value,
    (current) => current,
    (raw) => (HEX_COLOR_PATTERN.test(raw) ? raw.toUpperCase() : null),
    onCommit,
  )

  return (
    <div className="flex flex-col gap-1">
      {label && (
        <Label htmlFor={fieldId} className="text-xs text-muted-foreground">
          {label}
        </Label>
      )}
      <div className="flex items-center gap-1.5">
        <span
          aria-hidden="true"
          className="size-5 shrink-0 rounded border border-border"
          style={{ backgroundColor: HEX_COLOR_PATTERN.test(text) ? `#${text}` : undefined }}
        />
        <Input
          id={fieldId}
          value={text}
          disabled={disabled}
          maxLength={6}
          aria-label={label ? undefined : ariaLabel}
          aria-invalid={isInvalid}
          aria-describedby={isInvalid ? errorId : undefined}
          onChange={(event) => handleChange(event.target.value)}
          className="h-7 text-xs uppercase"
        />
      </div>
      {isInvalid && (
        <span id={errorId} role="alert" className="text-xs text-destructive">
          {t('documentLayouts.editor.shared.colorInvalid')}
        </span>
      )}
    </div>
  )
}
