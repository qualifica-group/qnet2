import { useTranslation } from 'react-i18next'
import { PhoneCall } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { DateTimeField } from '@/components/date-time-field'
import { FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'

interface RequestCallbackSectionProps {
  control: Control<RequestWorkFormValues>
}

/**
 * The operator's next scheduled follow-up call (spec 0052 D-1/D-5), PATCHed
 * sparse via `next_callback_at`. `null` clears the plan. The HOUR is optional
 * (user directive 2026-07-31): a date saved without one commits midnight, so
 * the wire value keeps its single `Y-m-d\TH:i` shape. Read-only users get the
 * control disabled, same `MetaField` gating as every other field of this
 * panel (AC-008).
 */
export function RequestCallbackSection({ control }: RequestCallbackSectionProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={PhoneCall}
      title={t('requestManagement.workPanel.callback.title', { defaultValue: 'Next callback' })}
      description={t('requestManagement.workPanel.callback.description', {
        defaultValue: 'Plan the next follow-up call with the client.',
      })}
    >
      <MetaField
        control={control}
        name="next_callback_at"
        metaKey="next_callback_at"
        label={t('requestManagement.workPanel.callback.label', { defaultValue: 'Callback date' })}
      >
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <DateTimeField
              disabled={disabled}
              readOnly={readOnly}
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
        )}
      </MetaField>
    </FormSection>
  )
}
