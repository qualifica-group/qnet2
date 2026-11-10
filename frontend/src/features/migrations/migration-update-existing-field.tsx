import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'

interface MigrationUpdateExistingFieldProps {
  checked: boolean
  onCheckedChange: (checked: boolean) => void
  /** Explains the scope of the realignment (differs between single and mass import). */
  help: string
  disabled?: boolean
}

/**
 * "Also update records already imported" checkbox shared by the single and
 * the mass import dialogs (spec 0372). Off by default.
 */
export function MigrationUpdateExistingField({
  checked,
  onCheckedChange,
  help,
  disabled = false,
}: MigrationUpdateExistingFieldProps) {
  const { t } = useTranslation('migrations')
  const id = useId()
  const helpId = `${id}-help`

  return (
    <div className="flex flex-col gap-1.5">
      <div className="flex items-center gap-2">
        <Checkbox
          id={id}
          checked={checked}
          disabled={disabled}
          onCheckedChange={(value) => onCheckedChange(value === true)}
          aria-describedby={helpId}
        />
        <Label htmlFor={id} className="text-sm">
          {t('updateExisting.label')}
        </Label>
      </div>
      <p id={helpId} className="text-xs text-muted-foreground">
        {help}
      </p>
    </div>
  )
}
