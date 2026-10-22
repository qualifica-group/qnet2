import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { SegmentedControl } from '@/components/ui/segmented-control'
import { allowedAbsenceModes } from '@/features/absences/requests/absence-request-payload'
import {
  ABSENCE_MODES,
  type AbsenceRequestFormValues,
} from '@/features/absences/requests/absence-request-schema'
import { ABSENCE_DAY_PARTS, type AbsenceTypeOption } from '@/features/absences/types'

interface AbsenceRequestModeFieldProps {
  pickedType: AbsenceTypeOption | undefined
}

/** Whole day / half day / hours, limited to what the type admits, and the morning/afternoon choice of a half day. */
export function AbsenceRequestModeField({ pickedType }: AbsenceRequestModeFieldProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<AbsenceRequestFormValues>()
  const allowed = allowedAbsenceModes(pickedType)
  const options = ABSENCE_MODES.filter((mode) => allowed.includes(mode)).map((mode) => ({
    value: mode,
    label: t(`absences.requests.form.modes.${mode}`),
  }))
  const dayPartOptions = ABSENCE_DAY_PARTS.map((part) => ({
    value: part,
    label: t(`absences.requests.form.dayParts.${part}`),
  }))

  return (
    <>
      <FormField
        control={control}
        name="mode"
        render={({ field }) => (
          <FormItem>
            <FormLabel id="absence-request-mode-label">{t('absences.requests.form.modeLabel')}</FormLabel>
            {options.length === 0 ? (
              <p role="alert" className="text-sm text-destructive">
                {t('absences.requests.form.noModeAllowed')}
              </p>
            ) : (
              <FormControl>
                <SegmentedControl
                  aria-labelledby="absence-request-mode-label"
                  value={field.value}
                  options={options}
                  onValueChange={field.onChange}
                />
              </FormControl>
            )}
            <FormMessage />
          </FormItem>
        )}
      />
      <HalfDayPart options={dayPartOptions} />
    </>
  )
}

function HalfDayPart({ options }: { options: { value: (typeof ABSENCE_DAY_PARTS)[number]; label: string }[] }) {
  const { t } = useTranslation()
  const { control } = useFormContext<AbsenceRequestFormValues>()
  const mode = useWatch({ control, name: 'mode' })
  if (mode !== 'half_day') {
    return null
  }

  return (
    <FormField
      control={control}
      name="day_part"
      render={({ field }) => (
        <FormItem>
          <FormLabel id="absence-request-day-part-label">{t('absences.requests.form.dayPartLabel')}</FormLabel>
          <FormControl>
            <SegmentedControl
              aria-labelledby="absence-request-day-part-label"
              value={field.value}
              options={options}
              onValueChange={field.onChange}
            />
          </FormControl>
          <FormMessage />
        </FormItem>
      )}
    />
  )
}
