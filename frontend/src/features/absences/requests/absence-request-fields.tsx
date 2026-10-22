import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { CalendarDays, Paperclip } from 'lucide-react'
import { RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import {
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { AbsenceInsightPanel } from '@/features/absences/requests/absence-insight-panel'
import { AbsenceRequestModeField } from '@/features/absences/requests/absence-request-mode-field'
import { AbsenceRequestPersonField } from '@/features/absences/requests/absence-request-person-field'
import { allowedAbsenceModes } from '@/features/absences/requests/absence-request-payload'
import type { AbsenceRequestFormValues } from '@/features/absences/requests/absence-request-schema'
import type { AbsenceRequestPreview } from '@/features/absences/requests/use-absence-request-preview'
import type { AbsenceTypeOption } from '@/features/absences/types'
import { useAbilities } from '@/features/auth/use-abilities'

const MANAGE_ALL = 'absence-requests.manageAll'

interface AbsenceRequestFieldsProps {
  types: AbsenceTypeOption[]
  typesError: boolean
  /** Loaded with no active type: the select would open empty without telling why. */
  typesEmpty: boolean
  /** Live preview of the request, shown under the dates (see `useAbsenceRequestPreview`). */
  preview: AbsenceRequestPreview
  /** Editing a draft: the person is fixed, so it is not asked. */
  isEdit: boolean
  /** The file input, owned by the form (the file is not a form value): shown in the notes section. */
  attachment: ReactNode
}

/**
 * The request form in two sections (spec 0287 D-4): the absence (person, type, mode, dates or hourly duration,
 * live preview), then the note and the attachment.
 */
export function AbsenceRequestFields({ types, typesError, typesEmpty, preview, isEdit, attachment }: AbsenceRequestFieldsProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const { control, getValues, setValue } = useFormContext<AbsenceRequestFormValues>()
  const [typeId, mode] = useWatch({ control, name: ['absence_type_id', 'mode'] })
  const pickedType = types.find((type) => type.id === typeId)

  // A type that does not admit the current mode moves the form to the first one it admits
  function pickType(id: number) {
    const allowed = allowedAbsenceModes(types.find((type) => type.id === id))
    if (allowed.length > 0 && !allowed.includes(getValues('mode'))) {
      setValue('mode', allowed[0])
    }
  }

  return (
    <RecordSectionsGrid>
      <RecordSection title={t('absences.requests.form.sections.absence')} icon={<CalendarDays />}>
        {!isEdit && can(MANAGE_ALL) ? <AbsenceRequestPersonField /> : null}
        <FormField
          control={control}
          name="absence_type_id"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>{t('absences.requests.form.type')}</FormLabel>
              <Select
                value={field.value === null ? '' : String(field.value)}
                onValueChange={(value) => {
                  field.onChange(Number(value))
                  pickType(Number(value))
                }}
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
        <AbsenceRequestModeField pickedType={pickedType} />

        <div className="grid gap-3 sm:grid-cols-2">
          <FormField
            control={control}
            name="starts_on"
            render={({ field }) => (
              <FormItem>
                <FormLabel required>
                  {mode === 'full_day' ? t('absences.requests.form.startsOn') : t('absences.requests.form.date')}
                </FormLabel>
                <FormControl>
                  <Input type="date" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          {mode === 'full_day' ? (
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
          ) : mode === 'hours' ? (
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
          ) : null}
        </div>
        <AbsenceInsightPanel {...preview} variant="preview" />
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
