import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { CalendarDays, Paperclip } from 'lucide-react'
import { RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import {
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { AbsenceBalanceHint } from '@/features/absences/requests/absence-request-balance-hint'
import type { AbsenceRequestFormValues } from '@/features/absences/requests/absence-request-schema'
import type { AbsenceTypeOption } from '@/features/absences/types'

interface AbsenceRequestFieldsProps {
  types: AbsenceTypeOption[]
  typesError: boolean
  /** Loaded with no active type: the select would open empty without telling why. */
  typesEmpty: boolean
  /** The file input, owned by the form (the file is not a form value): shown in the notes section. */
  attachment: ReactNode
}

/**
 * The request form in two sections (spec 0287 D-4): the absence (type, balance, full-day switch,
 * dates or hourly duration), then the note and the attachment.
 */
export function AbsenceRequestFields({ types, typesError, typesEmpty, attachment }: AbsenceRequestFieldsProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<AbsenceRequestFormValues>()
  const [typeId, isFullDay] = useWatch({ control, name: ['absence_type_id', 'is_full_day'] })
  const pickedType = types.find((type) => type.id === typeId)

  return (
    <RecordSectionsGrid>
      <RecordSection title={t('absences.requests.form.sections.absence')} icon={<CalendarDays />}>
        <FormField
          control={control}
          name="absence_type_id"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>{t('absences.requests.form.type')}</FormLabel>
              <Select
                value={field.value === null ? '' : String(field.value)}
                onValueChange={(value) => field.onChange(Number(value))}
              >
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder={t('absences.requests.form.typePlaceholder')} />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {types.map((type) => (
                    <SelectItem key={type.id} value={String(type.id)}>
                      {type.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {typesError ? (
                <p role="alert" className="text-sm text-destructive">
                  {t('absences.requests.form.typesError')}
                </p>
              ) : null}
              {typesEmpty ? (
                <p className="text-sm text-muted-foreground">{t('absences.requests.form.typesEmpty')}</p>
              ) : null}
              <FormMessage />
            </FormItem>
          )}
        />
        {pickedType ? <AbsenceBalanceHint type={pickedType} /> : null}

        <FormField
          control={control}
          name="is_full_day"
          render={({ field }) => (
            <FormItem>
              <div className="flex items-center justify-between gap-3">
                <FormLabel>{t('absences.requests.form.fullDay')}</FormLabel>
                <FormControl>
                  <Switch checked={field.value} onCheckedChange={field.onChange} />
                </FormControl>
              </div>
              <FormDescription className="text-xs">{t('absences.requests.form.fullDayHint')}</FormDescription>
            </FormItem>
          )}
        />

        <div className="grid gap-3 sm:grid-cols-2">
          <FormField
            control={control}
            name="starts_on"
            render={({ field }) => (
              <FormItem>
                <FormLabel required>
                  {isFullDay ? t('absences.requests.form.startsOn') : t('absences.requests.form.date')}
                </FormLabel>
                <FormControl>
                  <Input type="date" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          {isFullDay ? (
            <FormField
              control={control}
              name="ends_on"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{t('absences.requests.form.endsOn')}</FormLabel>
                  <FormControl>
                    <Input type="date" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          ) : (
            <FormField
              control={control}
              name="requested_minutes"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{t('absences.requests.form.duration')}</FormLabel>
                  <FormControl>
                    <Input type="time" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          )}
        </div>
      </RecordSection>

      <RecordSection title={t('absences.requests.form.sections.notes')} icon={<Paperclip />}>
        <FormField
          control={control}
          name="note"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('absences.requests.form.note')}</FormLabel>
              <FormControl>
                <Textarea rows={3} {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        {attachment}
      </RecordSection>
    </RecordSectionsGrid>
  )
}
