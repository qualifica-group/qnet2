import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { EmailRecipientsInput } from '@/components/ui/email-recipients-input'
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import type { WorkflowStatusNotify } from '@/features/workflow-statuses/types'
import {
  blankStatusNotify,
  buildStatusNotifySchema,
  STATUS_NOTIFY_MAX_EMAILS,
  STATUS_NOTIFY_MESSAGE_MAX,
  STATUS_NOTIFY_SUBJECT_MAX,
} from '@/features/workflow-statuses/workflow-status-notify'

interface WorkflowStatusNotifyDialogProps {
  open: boolean
  statusName: string
  value: WorkflowStatusNotify | null
  onOpenChange: (open: boolean) => void
  onSave: (notify: WorkflowStatusNotify) => void
}

/**
 * Configures the email a working status sends (spec 0419): subject, message
 * and recipients. Not a `<form>`: the editor already sits inside the workflow
 * form, and a submit event would bubble to it through the portal.
 */
export function WorkflowStatusNotifyDialog({ open, statusName, value, onOpenChange, onSave }: WorkflowStatusNotifyDialogProps) {
  const { t } = useTranslation()

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent size="md" className="gap-0 p-0">
        <DialogHeader className="border-b bg-surface px-4 py-3">
          <DialogTitle className="text-base">{t('workflowStatuses.notify.title')}</DialogTitle>
          <DialogDescription className="text-xs">
            {t('workflowStatuses.notify.description', { status: statusName || t('workflowStatuses.notify.thisStatus') })}
          </DialogDescription>
        </DialogHeader>
        {open ? <NotifyForm value={value} onCancel={() => onOpenChange(false)} onSave={onSave} /> : null}
      </DialogContent>
    </Dialog>
  )
}

interface NotifyFormProps {
  value: WorkflowStatusNotify | null
  onCancel: () => void
  onSave: (notify: WorkflowStatusNotify) => void
}

/** Mounted on every opening, so the form starts from the row's current configuration. */
function NotifyForm({ value, onCancel, onSave }: NotifyFormProps) {
  const { t } = useTranslation()
  const form = useForm<WorkflowStatusNotify>({
    resolver: zodResolver(buildStatusNotifySchema(t)),
    defaultValues: value ?? blankStatusNotify(),
  })
  const selectLabels = {
    searchPlaceholder: t('workflowStatuses.notify.search'),
    empty: t('workflowStatuses.notify.empty'),
    error: t('workflowStatuses.notify.loadError'),
    removeLabel: t('workflowStatuses.notify.remove'),
    retry: t('common.retry'),
  }

  return (
    <Form {...form}>
      <div className="flex max-h-[70vh] flex-col gap-3 overflow-y-auto px-4 py-3">
        <FormField
          control={form.control}
          name="subject"
          render={({ field }) => (
            <FormItem>
              <FormLabel required className="text-xs">{t('workflowStatuses.notify.subject')}</FormLabel>
              <FormControl>
                <Input {...field} maxLength={STATUS_NOTIFY_SUBJECT_MAX} className="h-8 text-xs" />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="message"
          render={({ field }) => (
            <FormItem>
              <FormLabel required className="text-xs">{t('workflowStatuses.notify.message')}</FormLabel>
              <FormControl>
                <Textarea {...field} rows={5} maxLength={STATUS_NOTIFY_MESSAGE_MAX} className="min-h-24 text-xs" />
              </FormControl>
              <FormDescription className="text-xs">{t('workflowStatuses.notify.messageHint')}</FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />

        <span className="text-xs font-medium">{t('workflowStatuses.notify.recipients')}</span>
        <FormField
          control={form.control}
          name="record_team"
          render={({ field }) => (
            <FormItem>
              <label className="flex items-center gap-2 text-xs">
                <Checkbox checked={field.value} onCheckedChange={(checked) => field.onChange(checked === true)} />
                {t('workflowStatuses.notify.recordTeam')}
              </label>
              <FormDescription className="text-xs">{t('workflowStatuses.notify.recordTeamHint')}</FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />
        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
          <FormField
            control={form.control}
            name="user_ids"
            render={({ field }) => (
              <FormItem>
                <FormLabel className="text-xs">{t('workflowStatuses.notify.users')}</FormLabel>
                <FormControl>
                  <AsyncPaginatedMultiSelect
                    resource="users"
                    value={field.value}
                    onChange={field.onChange}
                    showAvatar
                    labels={{
                      ...selectLabels,
                      placeholder: t('workflowStatuses.notify.usersPlaceholder'),
                      triggerLabel: t('workflowStatuses.notify.users'),
                    }}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="role_ids"
            render={({ field }) => (
              <FormItem>
                <FormLabel className="text-xs">{t('workflowStatuses.notify.roles')}</FormLabel>
                <FormControl>
                  <AsyncPaginatedMultiSelect
                    resource="roles"
                    value={field.value}
                    onChange={field.onChange}
                    labels={{
                      ...selectLabels,
                      placeholder: t('workflowStatuses.notify.rolesPlaceholder'),
                      triggerLabel: t('workflowStatuses.notify.roles'),
                    }}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>
        <FormField
          control={form.control}
          name="emails"
          render={({ field }) => (
            <FormItem>
              <FormLabel className="text-xs">{t('workflowStatuses.notify.emails')}</FormLabel>
              <FormControl>
                <EmailRecipientsInput
                  value={field.value}
                  onChange={field.onChange}
                  maxItems={STATUS_NOTIFY_MAX_EMAILS}
                  placeholder={t('workflowStatuses.notify.emailsPlaceholder')}
                  removeLabel={t('workflowStatuses.notify.remove')}
                  onInvalidInput={() => toast.error(t('workflowStatuses.notify.emailInvalid'))}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
      </div>
      <DialogFooter className="border-t bg-surface px-4 py-3">
        <Button type="button" variant="outline" size="sm" className="bg-card" onClick={onCancel}>
          {t('common.cancel')}
        </Button>
        <Button type="button" size="sm" onClick={() => void form.handleSubmit(onSave)()}>
          {t('workflowStatuses.notify.save')}
        </Button>
      </DialogFooter>
    </Form>
  )
}
