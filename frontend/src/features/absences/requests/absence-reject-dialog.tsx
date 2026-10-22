import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Textarea } from '@/components/ui/textarea'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { buildRejectSchema, type RejectFormValues } from '@/features/absences/requests/absence-request-schema'
import { serverMessage } from '@/features/absences/requests/absence-server-errors'
import { toast } from 'sonner'

interface AbsenceRejectDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Rejects with the reason; a 422 rejection is wired to the field here. */
  onReject: (reason: string) => Promise<unknown>
  isPending: boolean
  /** Overrides for the multiple rejection (one reason for many requests). */
  title?: string
  description?: string
}

/** Reject dialog: the reason is mandatory (client Zod rule, server 422 shown on the same field). */
export function AbsenceRejectDialog({ open, onOpenChange, onReject, isPending, title, description }: AbsenceRejectDialogProps) {
  const { t } = useTranslation()
  const schema = useMemo(() => buildRejectSchema(t), [t])
  const form = useForm<RejectFormValues>({ resolver: zodResolver(schema), defaultValues: { rejection_reason: '' } })

  const submit = form.handleSubmit(async (values) => {
    try {
      await onReject(values.rejection_reason)
      onOpenChange(false)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, ['rejection_reason'])) {
        toast.error(serverMessage(error) ?? t('absences.requests.actions.error'))
      }
    }
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{title ?? t('absences.requests.reject.title')}</DialogTitle>
          <DialogDescription>{description ?? t('absences.requests.reject.description')}</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form onSubmit={(event) => void submit(event)} noValidate className="space-y-3">
            <FormField
              control={form.control}
              name="rejection_reason"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{t('absences.requests.reject.reason')}</FormLabel>
                  <FormControl>
                    <Textarea rows={4} {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                {t('absences.requests.reject.close')}
              </Button>
              <Button type="submit" variant="destructive" disabled={isPending}>
                {t('absences.requests.reject.confirm')}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  )
}
