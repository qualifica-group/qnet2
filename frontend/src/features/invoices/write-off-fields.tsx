import type { Control, FieldPath, FieldValues } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { formatEuro } from '@/features/invoices/invoice-format'
import { WRITE_OFF_NOTE_MAX, WRITE_OFF_REASONS } from '@/features/invoices/types'
import type { WriteOffFormFields } from '@/features/invoices/write-off'

interface WriteOffFieldsProps<T extends FieldValues & WriteOffFormFields> {
  control: Control<T>
  /** Read-only amount that will be closed: installment amount minus the settled one. */
  writeOffAmount: number
  /** Prefix of the radio ids, unique per dialog. */
  idPrefix: string
}

/** Reason (required), note and read-only amount of the "write-off / bad debt" choice (spec 0361). */
export function WriteOffFields<T extends FieldValues & WriteOffFormFields>({
  control,
  writeOffAmount,
  idPrefix,
}: WriteOffFieldsProps<T>) {
  const { t } = useTranslation()
  const reasonName = 'write_off_reason' as FieldPath<T>
  const noteName = 'write_off_note' as FieldPath<T>

  return (
    <div className="flex flex-col gap-2 border-t border-border pt-2">
      <p className="text-xs">
        <span className="text-muted-foreground">{t('writeOff.amount')}: </span>
        <span className="font-medium">{formatEuro(writeOffAmount)}</span>
      </p>
      <FormField
        control={control}
        name={reasonName}
        render={({ field }) => (
          <FormItem className="gap-1">
            <fieldset className="flex flex-col gap-1.5">
              <legend className="mb-1 text-xs font-medium">{t('writeOff.reason')}</legend>
              {WRITE_OFF_REASONS.map((reason) => {
                const inputId = `${idPrefix}-write-off-reason-${reason}`
                return (
                  <label key={reason} htmlFor={inputId} className="flex items-center gap-2 text-xs">
                    <input
                      id={inputId}
                      type="radio"
                      name={field.name}
                      value={reason}
                      checked={field.value === reason}
                      onChange={() => field.onChange(reason)}
                      onBlur={field.onBlur}
                      className="size-3.5 accent-primary"
                    />
                    {t(`writeOff.reasons.${reason}`)}
                  </label>
                )
              })}
            </fieldset>
            <FormMessage className="text-xs" />
          </FormItem>
        )}
      />
      <FormField
        control={control}
        name={noteName}
        render={({ field }) => (
          <FormItem className="gap-1">
            <FormLabel className="text-xs">{t('writeOff.note')}</FormLabel>
            <FormControl>
              <Input type="text" maxLength={WRITE_OFF_NOTE_MAX + 1} {...field} value={String(field.value ?? '')} />
            </FormControl>
            <FormMessage className="text-xs" />
          </FormItem>
        )}
      />
    </div>
  )
}
