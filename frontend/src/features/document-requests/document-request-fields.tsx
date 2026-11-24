import { useTranslation } from 'react-i18next'
import { useFormContext } from 'react-hook-form'
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { REQUEST_MESSAGE_MAX_LENGTH, type DocumentRequestFormValues } from '@/features/document-requests/document-request-schema'
import { todayIsoDate } from '@/features/work-order-costs/work-order-costs-schema'

/** Due date, recipient, message and send flag: the fields shared by the create and the edit dialog. */
export function DocumentRequestFields() {
  const { t } = useTranslation()
  const form = useFormContext<DocumentRequestFormValues>()

  return (
    <>
      <div className="grid gap-3 sm:grid-cols-2">
        <FormField
          control={form.control}
          name="due_date"
          render={({ field }) => (
            <FormItem className="gap-1">
              <FormLabel>{t('documentRequests.form.dueDate')}</FormLabel>
              <FormControl>
                <Input {...field} type="date" min={todayIsoDate()} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="recipient_email"
          render={({ field }) => (
            <FormItem className="gap-1">
              <FormLabel>{t('documentRequests.form.recipientEmail')}</FormLabel>
              <FormControl>
                <Input {...field} type="email" autoComplete="off" />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
      </div>
      <FormField
        control={form.control}
        name="message"
        render={({ field }) => (
          <FormItem className="gap-1">
            <FormLabel>{t('documentRequests.form.message')}</FormLabel>
            <FormControl>
              <Textarea {...field} rows={3} maxLength={REQUEST_MESSAGE_MAX_LENGTH} />
            </FormControl>
            <FormDescription>
              {t('documentRequests.form.messageCount', { count: field.value.length, max: REQUEST_MESSAGE_MAX_LENGTH })}
            </FormDescription>
            <FormMessage />
          </FormItem>
        )}
      />
      <FormField
        control={form.control}
        name="send"
        render={({ field }) => (
          <FormItem className="flex-row items-center gap-2">
            <FormControl>
              <Switch checked={field.value} onCheckedChange={field.onChange} />
            </FormControl>
            <FormLabel>{t('documentRequests.form.send')}</FormLabel>
          </FormItem>
        )}
      />
    </>
  )
}
