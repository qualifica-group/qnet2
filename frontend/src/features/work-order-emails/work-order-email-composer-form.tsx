import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { LayoutTemplate } from 'lucide-react'
import type { UseFormReturn } from 'react-hook-form'
import { cn } from '@/lib/utils'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { EmailRecipientsInput, type EmailRecipientSuggestion } from '@/components/ui/email-recipients-input'
import { RichTextEditor } from '@/components/rich-text/rich-text-editor'
import { UserAvatar } from '@/components/user-avatar'
import { useApplyWorkOrderEmailTemplate } from '@/features/work-order-emails/use-apply-work-order-email-template'
import type { ComposeContext } from '@/features/work-order-emails/types'
import type { ComposerFormValues } from '@/features/work-order-emails/work-order-email-schema'

/** D-5: 50 addresses total across to/cc/bcc combined, not per field. */
const RECIPIENTS_TOTAL_MAX = 50

/** Stable module-level default: a fresh `[]` per render would break the input's memoized pool. */
const NO_SUGGESTIONS: EmailRecipientSuggestion[] = []

/**
 * One line of the "envelope" card (Da / A / CC / CCN / Oggetto): label column
 * on the left, borderless field on the right, hairline between lines. Focus is
 * marked by an inset bar on the line, since the field itself has no border.
 */
const ENVELOPE_ROW_CLASS =
  'grid grid-cols-[3.5rem_minmax(0,1fr)] items-start gap-x-3 gap-y-0.5 px-3 py-1 transition-shadow focus-within:shadow-[inset_2px_0_0_0_var(--ring)] sm:grid-cols-[4.5rem_minmax(0,1fr)]'
const ENVELOPE_LABEL_CLASS = 'gap-0 pt-3 text-xs leading-none font-medium text-muted-foreground'
const ENVELOPE_FIELD_CLASS = 'border-0 bg-transparent px-0 shadow-none focus-within:ring-0 focus-visible:ring-0'
const ENVELOPE_HINT_CLASS = 'col-start-2 pb-1 text-[11px]'

type RecipientField = 'to' | 'cc' | 'bcc'

interface WorkOrderEmailComposerFormProps {
  workOrderId: number
  form: UseFormReturn<ComposerFormValues>
  composeContext: ComposeContext | undefined
  disabled: boolean
}

/**
 * Pure fields of the composer (AC-020): template picker, then the envelope
 * (From readonly, A/CC/CCN, Oggetto) and the body. CC/CCN stay folded behind
 * two toggles until used, as in mail clients; a field that already holds
 * addresses is always shown. All business logic (template render + overwrite
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
  const [ccRequested, setCcRequested] = useState(false)
  const [bccRequested, setBccRequested] = useState(false)

  const recipients: Record<RecipientField, string[]> = {
    to: form.watch('to'),
    cc: form.watch('cc'),
    bcc: form.watch('bcc'),
  }
  const suggestions = composeContext?.recipient_suggestions ?? NO_SUGGESTIONS
  const isFieldDisabled = disabled || isRendering
  const isCcVisible = ccRequested || recipients.cc.length > 0
  const isBccVisible = bccRequested || recipients.bcc.length > 0

  const remainingFor = (field: RecipientField) =>
    RECIPIENTS_TOTAL_MAX -
    (Object.keys(recipients) as RecipientField[])
      .filter((other) => other !== field)
      .reduce((total, other) => total + recipients[other].length, 0)

  const renderRecipientsInput = (field: RecipientField, value: string[], onChange: (next: string[]) => void) => (
    <EmailRecipientsInput
      value={value}
      onChange={onChange}
      suggestions={suggestions}
      placeholder={t('workOrderEmails.composer.recipientsPlaceholder')}
      removeLabel={t(`workOrderEmails.composer.${field}`)}
      maxItems={remainingFor(field)}
      onInvalidInput={() => toast.error(t('workOrderEmails.composer.recipientInvalid'))}
      disabled={isFieldDisabled}
      className={ENVELOPE_FIELD_CLASS}
    />
  )

  return (
    <Form {...form}>
      <div className="flex flex-col gap-3">
        <div className="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:gap-3">
          <Label className="flex shrink-0 items-center gap-1.5 text-xs text-muted-foreground">
            <LayoutTemplate className="size-3.5" aria-hidden="true" />
            {t('workOrderEmails.composer.template')}
          </Label>
          <div className="w-full sm:max-w-xs">
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
        </div>

        <div className="divide-y divide-border rounded-lg border bg-card shadow-xs">
          <div className={ENVELOPE_ROW_CLASS}>
            <span className={ENVELOPE_LABEL_CLASS}>{t('workOrderEmails.composer.from')}</span>
            <div className="flex min-h-9 min-w-0 items-center gap-2 text-sm">
              {composeContext ? (
                <>
                  <UserAvatar name={composeContext.sender.name} size="sm" />
                  <span className="truncate font-medium">{composeContext.sender.name}</span>
                  <span className="truncate text-muted-foreground">{composeContext.sender.email}</span>
                </>
              ) : null}
            </div>
          </div>

          <FormField
            control={form.control}
            name="to"
            render={({ field }) => (
              <FormItem className={ENVELOPE_ROW_CLASS}>
                <FormLabel required className={ENVELOPE_LABEL_CLASS}>
                  {t('workOrderEmails.composer.to')}
                </FormLabel>
                <div className="flex min-w-0 items-start gap-1">
                  <div className="min-w-0 flex-1">
                    <FormControl>{renderRecipientsInput('to', field.value, field.onChange)}</FormControl>
                  </div>
                  <div className="flex shrink-0 gap-0.5 pt-1.5">
                    {!isCcVisible ? (
                      <Button
                        type="button"
                        variant="ghost"
                        size="xs"
                        aria-label={t('workOrderEmails.composer.addCc')}
                        onClick={() => setCcRequested(true)}
                        disabled={isFieldDisabled}
                      >
                        {t('workOrderEmails.composer.cc')}
                      </Button>
                    ) : null}
                    {!isBccVisible ? (
                      <Button
                        type="button"
                        variant="ghost"
                        size="xs"
                        aria-label={t('workOrderEmails.composer.addBcc')}
                        onClick={() => setBccRequested(true)}
                        disabled={isFieldDisabled}
                      >
                        {t('workOrderEmails.composer.bcc')}
                      </Button>
                    ) : null}
                  </div>
                </div>
                <FormDescription className={ENVELOPE_HINT_CLASS}>
                  {t('workOrderEmails.composer.recipientsMax')}
                </FormDescription>
                <FormMessage className={ENVELOPE_HINT_CLASS} />
              </FormItem>
            )}
          />

          {isCcVisible ? (
            <FormField
              control={form.control}
              name="cc"
              render={({ field }) => (
                <FormItem className={ENVELOPE_ROW_CLASS}>
                  <FormLabel className={ENVELOPE_LABEL_CLASS}>{t('workOrderEmails.composer.cc')}</FormLabel>
                  <FormControl>{renderRecipientsInput('cc', field.value, field.onChange)}</FormControl>
                  <FormMessage className={ENVELOPE_HINT_CLASS} />
                </FormItem>
              )}
            />
          ) : null}

          {isBccVisible ? (
            <FormField
              control={form.control}
              name="bcc"
              render={({ field }) => (
                <FormItem className={ENVELOPE_ROW_CLASS}>
                  <FormLabel className={ENVELOPE_LABEL_CLASS}>{t('workOrderEmails.composer.bcc')}</FormLabel>
                  <FormControl>{renderRecipientsInput('bcc', field.value, field.onChange)}</FormControl>
                  <FormMessage className={ENVELOPE_HINT_CLASS} />
                </FormItem>
              )}
            />
          ) : null}

          <FormField
            control={form.control}
            name="subject"
            render={({ field }) => (
              <FormItem className={ENVELOPE_ROW_CLASS}>
                <FormLabel className={ENVELOPE_LABEL_CLASS}>{t('workOrderEmails.composer.subject')}</FormLabel>
                <FormControl>
                  <Input
                    {...field}
                    maxLength={255}
                    disabled={isFieldDisabled}
                    placeholder={t('workOrderEmails.composer.subjectPlaceholder')}
                    className={cn(ENVELOPE_FIELD_CLASS, 'font-medium placeholder:font-normal')}
                  />
                </FormControl>
                <FormMessage className={ENVELOPE_HINT_CLASS} />
              </FormItem>
            )}
          />
        </div>

        <FormField
          control={form.control}
          name="body"
          render={({ field }) => (
            <FormItem>
              <FormLabel className="sr-only">{t('workOrderEmails.composer.body')}</FormLabel>
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
