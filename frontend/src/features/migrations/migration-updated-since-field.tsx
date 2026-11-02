import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

/** Local calendar day as YYYY-MM-DD (the `max` of the picker; `toISOString` would shift it to UTC). */
function todayInputValue(): string {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${now.getFullYear()}-${month}-${day}`
}

interface MigrationUpdatedSinceFieldProps {
  /** YYYY-MM-DD, or '' for a full import. */
  value: string
  onChange: (value: string) => void
  /** Server-side start error, wired to the field through the accessible error triad. */
  error?: string | null
  disabled?: boolean
}

/**
 * Optional "only records created or modified since" day shared by the single
 * and the mass import dialogs (spec 0345). Empty means a full import.
 */
export function MigrationUpdatedSinceField({
  value,
  onChange,
  error = null,
  disabled = false,
}: MigrationUpdatedSinceFieldProps) {
  const { t } = useTranslation('migrations')
  const id = useId()
  const helpId = `${id}-help`
  const errorId = `${id}-error`

  return (
    <div className="flex flex-col gap-1.5">
      <Label htmlFor={id} className="text-xs">
        {t('updatedSince.label')}
      </Label>
      <Input
        id={id}
        type="date"
        value={value}
        max={todayInputValue()}
        disabled={disabled}
        onChange={(event) => onChange(event.target.value)}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? `${helpId} ${errorId}` : helpId}
        className="w-full max-w-48"
      />
      <p id={helpId} className="text-xs text-muted-foreground">
        {t('updatedSince.help')}
      </p>
      {error ? (
        <span id={errorId} role="alert" className="text-xs text-destructive">
          {error}
        </span>
      ) : null}
    </div>
  )
}
