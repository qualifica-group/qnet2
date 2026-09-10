import { useTranslation } from 'react-i18next'
import { PhoneCall } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { DateTimeField } from '@/components/date-time-field'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import type { RequestCreateFormValues } from '@/features/request-management/request-create-schema'

interface RequestCreateCallbackSectionProps {
  control: Control<RequestCreateFormValues>
}

/**
 * The next scheduled follow-up call, planned already at creation (user
 * directive 2026-07-31): the SAME `DateTimeField` the work panel carries, with
 * the same optional hour — a date saved without one commits midnight, so the
 * wire value keeps its single `Y-m-d\TH:i` shape on both channels.
 *
 * Twin of `RequestCallbackSection` (plain `FormField` instead of `MetaField`)
 * for the same reason as every other `RequestCreate*` section: this create-only
 * form has no `permissions` envelope to gate against.
 */
export function RequestCreateCallbackSection({ control }: RequestCreateCallbackSectionProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={PhoneCall}
      title={t('requestManagement.workPanel.callback.title', { defaultValue: 'Next callback' })}
      description={t('requestManagement.workPanel.callback.description', {
        defaultValue: 'Plan the next follow-up call with the client.',
      })}
      className="min-w-0"
    >
      <FormField
        control={control}
        name="next_callback_at"
        render={({ field }) => (
          <FormItem>
            <FormLabel>
              {t('requestManagement.workPanel.callback.label', { defaultValue: 'Callback date' })}
            </FormLabel>
            <FormControl>
              <DateTimeField
                timeLabel={t('requestManagement.workPanel.callback.timeLabel', {
                  defaultValue: 'Callback time (optional)',
                })}
                value={field.value ?? null}
                onChange={field.onChange}
                onBlur={field.onBlur}
                name={field.name}
                ref={field.ref}
                className="max-w-md"
              />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />
    </FormSection>
  )
}
