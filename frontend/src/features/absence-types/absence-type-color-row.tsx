import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { absenceTypeDotClass } from '@/features/absences/absence-type-color'
import { ABSENCE_TYPE_COLORS, type AbsenceTypeColor } from '@/features/absences/types'
import type { AbsenceTypeFormValues } from '@/features/absence-types/absence-type-schema'
import { cn } from '@/lib/utils'

interface AbsenceTypeColorRowProps {
  inline: InlineEdit
  canEdit: boolean
}

/** The type's colour: dot + name when closed, a radio group of the palette (dot + name, never colour alone) when open. */
export function AbsenceTypeColorRow({ inline, canEdit }: AbsenceTypeColorRowProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<AbsenceTypeFormValues>()
  const color = useWatch({ control, name: 'color' })
  const label = t('absenceTypes.form.color')

  return (
    <RecordInlineField
      field="color"
      label={label}
      inline={inline}
      canEdit={canEdit}
      editor={
        <MetaField control={control} name="color" metaKey="color" label={label}>
          {({ field, disabled }) => (
            <FormControl>
              <div role="radiogroup" aria-label={label} className="flex flex-wrap gap-1.5">
                {ABSENCE_TYPE_COLORS.map((token) => (
                  <ColorOption
                    key={token}
                    token={token}
                    selected={field.value === token}
                    disabled={disabled}
                    onSelect={() => field.onChange(token)}
                  />
                ))}
              </div>
            </FormControl>
          )}
        </MetaField>
      }
    >
      <span className="flex items-center gap-2">
        <span className={cn('size-3.5 shrink-0 rounded-full', absenceTypeDotClass(color))} aria-hidden="true" />
        {t(`absenceTypes.colors.${color}`)}
      </span>
    </RecordInlineField>
  )
}

interface ColorOptionProps {
  token: AbsenceTypeColor
  selected: boolean
  disabled: boolean
  onSelect: () => void
}

function ColorOption({ token, selected, disabled, onSelect }: ColorOptionProps) {
  const { t } = useTranslation()
  return (
    <button
      type="button"
      role="radio"
      aria-checked={selected}
      disabled={disabled}
      onClick={onSelect}
      className={cn(
        'flex items-center gap-1.5 rounded-md border border-input bg-card px-2 py-1 text-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50',
        selected && 'border-ring ring-2 ring-ring/40',
      )}
    >
      <span className={cn('size-3.5 shrink-0 rounded-full', absenceTypeDotClass(token))} aria-hidden="true" />
      {t(`absenceTypes.colors.${token}`)}
    </button>
  )
}
