import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { updateProfile } from '@/features/auth/api'
import { authKeys } from '@/features/auth/query-keys'
import { useAuth } from '@/features/auth/use-auth'
import {
  DATE_FORMATS,
  TIME_FORMATS,
  formatDateTimeWith,
  formatDateWith,
  toDateFormat,
  toTimeFormat,
  type DateFormat,
  type TimeFormat,
} from '@/lib/formatting/date-display'

const DATE_FIELD_ID = 'date-format-select'
const TIME_FIELD_ID = 'time-format-select'

/** A fixed instant, so each option's sample reads the same on every machine. */
const SAMPLE = '2026-08-03T14:30:00'

/**
 * Settings section for the per-user date/time display preferences. Saving sends
 * a partial PATCH /auth/me and primes the ['auth','me'] cache, after which
 * DateDisplayProvider re-renders the app with the new patterns.
 *
 * Each option carries a live sample of the SAME instant so the choice is read
 * from the result, not from a pattern notation the user has to decode.
 */
export function DateFormatForm() {
  const { t } = useTranslation()
  const { user } = useAuth()
  const queryClient = useQueryClient()
  const [dateFormat, setDateFormat] = useState<DateFormat>(() => toDateFormat(user?.date_format))
  const [timeFormat, setTimeFormat] = useState<TimeFormat>(() => toTimeFormat(user?.time_format))
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const isDirty =
    dateFormat !== toDateFormat(user?.date_format) || timeFormat !== toTimeFormat(user?.time_format)

  const onSave = async () => {
    setIsSaving(true)
    setError(null)
    try {
      const updatedUser = await updateProfile({ date_format: dateFormat, time_format: timeFormat })
      queryClient.setQueryData(authKeys.me, updatedUser)
      toast.success(t('settings.dateFormat.saved'))
    } catch {
      setError(t('settings.genericError'))
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-1">
        <h3 className="text-base font-semibold">{t('settings.dateFormat.title')}</h3>
        <p className="text-sm text-muted-foreground">{t('settings.dateFormat.subtitle')}</p>
      </div>

      <div className="grid gap-3 sm:grid-cols-2">
        <div className="flex flex-col gap-2">
          <Label htmlFor={DATE_FIELD_ID}>{t('settings.dateFormat.dateLabel')}</Label>
          <Select value={dateFormat} onValueChange={(value) => setDateFormat(toDateFormat(value))}>
            <SelectTrigger id={DATE_FIELD_ID} className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {DATE_FORMATS.map((option) => (
                <SelectItem key={option} value={option}>
                  {t(`settings.dateFormat.options.${option}`)}
                  <span className="text-muted-foreground">{formatDateWith(SAMPLE, option)}</span>
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor={TIME_FIELD_ID}>{t('settings.dateFormat.timeLabel')}</Label>
          <Select value={timeFormat} onValueChange={(value) => setTimeFormat(toTimeFormat(value))}>
            <SelectTrigger id={TIME_FIELD_ID} className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {TIME_FORMATS.map((option) => (
                <SelectItem key={option} value={option}>
                  {t(`settings.dateFormat.options.${option}`)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      <p className="text-sm text-muted-foreground" aria-live="polite">
        {t('settings.dateFormat.preview', {
          dateTime: formatDateTimeWith(SAMPLE, dateFormat, timeFormat),
        })}
      </p>

      {error && (
        <p className="text-sm font-medium text-destructive" role="alert">
          {error}
        </p>
      )}

      <div>
        <Button type="button" onClick={() => void onSave()} disabled={isSaving || !isDirty}>
          {isSaving ? t('settings.savingProfile') : t('settings.saveProfile')}
        </Button>
      </div>
    </div>
  )
}

