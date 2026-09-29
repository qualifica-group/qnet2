import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { UseFormReturn } from 'react-hook-form'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { EmailRecipientsInput, type EmailRecipientSuggestion } from '@/components/ui/email-recipients-input'
import { RichTextEditor } from '@/components/rich-text/rich-text-editor'
import { useApplyWorkOrderEmailTemplate } from '@/features/work-order-emails/use-apply-work-order-email-template'
import type { ComposeContext } from '@/features/work-order-emails/types'
import type { ComposerFormValues } from '@/features/work-order-emails/work-order-email-schema'

/** D-5: 50 addresses total across to/cc/bcc combined, not per field. */
const RECIPIENTS_TOTAL_MAX = 50

/** Stable module-level default: a fresh `[]` per render would break the input's memoized pool. */
const NO_SUGGESTIONS: EmailRecipientSuggestion[] = []

interface WorkOrderEmailComposerFormProps {
  workOrderId: number
  form: UseFormReturn<ComposerFormValues>
  composeContext: ComposeContext | undefined
  disabled: boolean
}

/**
 * Pure fields of the composer (AC-020): From (readonly), template picker,
 * A/CC/CCN, Oggetto, Corpo. All business logic (template render + overwrite
 * confirm) lives in `useApplyWorkOrderEmailTemplate` — this component only
 * wires the field to it.
 */
export function WorkOrderEmailComposerForm({
  workOrderId,
  form,
  composeContext,
  disabled,
}: WorkOrderEmailComposerFormProps) {
  const { t } = useTranslation()
  const { applyTemplate, isRendering } = useApplyWorkOrderEmailTemplate(workOrderId, form)

  const toValue = form.watch('to')
  const ccValue = form.watch('cc')
  const bccValue = form.watch('bcc')
  const suggestions = composeContext?.recipient_suggestions ?? NO_SUGGESTIONS
  const isFieldDisabled = disabled || isRendering

  return (
    <Form {...form}>
      <div className="flex flex-col gap-4">
        <div className="grid gap-2">
          <Label>{t('workOrderEmails.composer.from')}</Label>
          <Input value={composeContext?.sender.email ?? ''} disabled readOnly />
        </div>

        <div className="grid gap-2">
          <Label>{t('workOrderEmails.composer.template')}</Label>
          <AsyncPaginatedSelect
            resource="email-templates"
            params={{ module: 'work_orders' }}
            value={form.watch('email_template_id')}
            onChange={(id) => {
              if (id === null) {
                form.setValue('email_template_id', null, { shouldDirty: true })
                return
              }
              void applyTemplate(id)
            }}
            labels={{
              placeholder: t('workOrderEmails.composer.templatePlaceholder'),
              searchPlaceholder: t('workOrderEmails.composer.templateSearch'),
              empty: t('workOrderEmails.composer.templateEmpty'),
              error: t('workOrderEmails.composer.templateError'),
              retry: t('common.retry'),
              clearLabel: t('workOrderEmails.composer.attachments.remove'),
              triggerLabel: t('workOrderEmails.composer.template'),
            }}
            disabled={isFieldDisabled}
          />
        </div>

        <FormField
          control={form.control}
          name="to"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>{t('workOrderEmails.composer.to')}</FormLabel>
              <FormControl>
                <EmailRecipientsInput
                  value={field.value}
                  onChange={field.onChange}
                  suggestions={suggestions}
                  placeholder={t('workOrderEmails.composer.recipientsPlaceholder')}
                  removeLabel={t('workOrderEmails.composer.to')}
                  maxItems={RECIPIENTS_TOTAL_MAX - ccValue.length - bccValue.length}
                  onInvalidInput={() => toast.error(t('workOrderEmails.composer.recipientInvalid'))}
                  disabled={isFieldDisabled}
                />
              </FormControl>
              <FormDescription>{t('workOrderEmails.composer.recipientsMax')}</FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="cc"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('workOrderEmails.composer.cc')}</FormLabel>
              <FormControl>
                <EmailRecipientsInput
                  value={field.value}
                  onChange={field.onChange}
                  suggestions={suggestions}
                  placeholder={t('workOrderEmails.composer.recipientsPlaceholder')}
                  removeLabel={t('workOrderEmails.composer.cc')}
                  maxItems={RECIPIENTS_TOTAL_MAX - toValue.length - bccValue.length}
                  onInvalidInput={() => toast.error(t('workOrderEmails.composer.recipientInvalid'))}
                  disabled={isFieldDisabled}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="bcc"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('workOrderEmails.composer.bcc')}</FormLabel>
              <FormControl>
                <EmailRecipientsInput
                  value={field.value}
                  onChange={field.onChange}
                  suggestions={suggestions}
                  placeholder={t('workOrderEmails.composer.recipientsPlaceholder')}
                  removeLabel={t('workOrderEmails.composer.bcc')}
                  maxItems={RECIPIENTS_TOTAL_MAX - toValue.length - ccValue.length}
                  onInvalidInput={() => toast.error(t('workOrderEmails.composer.recipientInvalid'))}
                  disabled={isFieldDisabled}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="subject"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('workOrderEmails.composer.subject')}</FormLabel>
              <FormControl>
                <Input {...field} maxLength={255} disabled={isFieldDisabled} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="body"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('workOrderEmails.composer.body')}</FormLabel>
              <FormControl>
                <RichTextEditor value={field.value} onChange={field.onChange} allowImages={false} disabled={isFieldDisabled} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
      </div>
    </Form>
  )
}
