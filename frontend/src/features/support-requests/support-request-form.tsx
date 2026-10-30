import { useId } from 'react'
import { Controller } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { SupportRequestAttachments } from '@/features/support-requests/support-request-attachments'
import { SupportRequestTopicPicker } from '@/features/support-requests/support-request-topic-picker'
import type { useSupportRequestForm } from '@/features/support-requests/use-support-request-form'
import type { SupportRequestTopic } from '@/features/support-requests/types'

interface SupportRequestFormProps {
  topics: SupportRequestTopic[]
  state: ReturnType<typeof useSupportRequestForm>
}

/** The two-step request form: pick the problem type, describe it, optionally flag urgency and attach files. */
export function SupportRequestForm({ topics, state }: SupportRequestFormProps) {
  const { t } = useTranslation()
  const descriptionId = useId()
  const descriptionErrorId = useId()
  const urgentId = useId()
  const { form, submit, isSubmitting, serverError, addFiles, removeFile } = state
  const { control, register, watch, formState } = form
  const descriptionError = formState.errors.description?.message
  const topicError = formState.errors.support_topic_id?.message

  return (
    <form onSubmit={submit} noValidate className="grid gap-4">
      <Controller
        control={control}
        name="support_topic_id"
        render={({ field }) => (
          <SupportRequestTopicPicker
            topics={topics}
            legend={t('supportRequests.stepTopic')}
            field={field}
            error={topicError}
          />
        )}
      />

      <div className="grid gap-2">
        <Label htmlFor={descriptionId}>{t('supportRequests.stepDescription')}</Label>
        <Textarea
          id={descriptionId}
          rows={4}
          placeholder={t('supportRequests.descriptionPlaceholder')}
          aria-invalid={descriptionError ? true : undefined}
          aria-describedby={descriptionError ? descriptionErrorId : undefined}
          {...register('description')}
        />
        {descriptionError ? (
          <span id={descriptionErrorId} role="alert" className="text-xs text-destructive">
            {descriptionError}
          </span>
        ) : null}
      </div>

      <div className="flex items-center gap-2">
        <Controller
          control={control}
          name="is_urgent"
          render={({ field }) => (
            <Switch id={urgentId} checked={field.value} onCheckedChange={field.onChange} />
          )}
        />
        <Label htmlFor={urgentId}>{t('supportRequests.urgentLabel')}</Label>
      </div>

      <SupportRequestAttachments
        files={watch('attachments')}
        error={formState.errors.attachments?.message}
        onAdd={addFiles}
        onRemove={removeFile}
      />

      {serverError ? (
        <p role="alert" className="text-sm text-destructive">
          {serverError}
        </p>
      ) : null}

      <Button type="submit" disabled={isSubmitting} className="w-full sm:ml-auto sm:w-auto">
        {isSubmitting ? t('supportRequests.submitting') : t('supportRequests.submit')}
      </Button>
    </form>
  )
}
