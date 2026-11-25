import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Textarea } from '@/components/ui/textarea'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  buildReviewSchema,
  REVIEW_NOTE_MAX_LENGTH,
  type ReviewFormValues,
} from '@/features/document-requests/document-request-review-schema'
import type {
  DocumentRequest,
  DocumentRequestItem,
  DocumentRequestReviewDecision,
} from '@/features/document-requests/types'
import { useReviewDocumentRequestItem } from '@/features/document-requests/use-document-requests'

interface DocumentRequestReviewDialogProps {
  request: DocumentRequest
  item: DocumentRequestItem
  decision: DocumentRequestReviewDecision
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** Manual approval (optional note) or rejection (mandatory note) of one item, overriding the AI outcome (spec 0429). */
export function DocumentRequestReviewDialog({ request, item, decision, open, onOpenChange }: DocumentRequestReviewDialogProps) {
  const { t } = useTranslation()
  const review = useReviewDocumentRequestItem(request.work_order_id, request.id)
  const form = useForm<ReviewFormValues>({
    resolver: zodResolver(buildReviewSchema(t, decision)),
    defaultValues: { note: '' },
  })

  function onSubmit(values: ReviewFormValues) {
    const note = values.note.trim()
    review.mutate(
      { itemId: item.id, decision, note: note === '' ? null : note },
      {
        onSuccess: () => {
          toast.success(t(`documentRequests.review.${decision}Queued`))
          onOpenChange(false)
        },
        onError: (error) => {
          if (!applyServerValidationErrors(error, form.setError, ['note'])) {
            toast.error(t(`documentRequests.review.${decision}Error`))
          }
        },
      },
    )
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{t(`documentRequests.review.${decision}Title`)}</DialogTitle>
          <DialogDescription>{t(`documentRequests.review.${decision}Description`, { name: item.name })}</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-3" noValidate>
            <FormField
              control={form.control}
              name="note"
              render={({ field }) => (
                <FormItem className="gap-1">
                  <FormLabel>
                    {t(decision === 'reject' ? 'documentRequests.review.noteRequired' : 'documentRequests.review.noteOptional')}
                  </FormLabel>
                  <FormControl>
                    <Textarea {...field} rows={3} maxLength={REVIEW_NOTE_MAX_LENGTH} />
                  </FormControl>
                  <FormDescription>
                    {t('documentRequests.form.messageCount', { count: field.value.length, max: REVIEW_NOTE_MAX_LENGTH })}
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />
            <DialogFooter>
              <Button type="button" variant="secondary" onClick={() => onOpenChange(false)}>
                {t('common.cancel')}
              </Button>
              <Button type="submit" variant={decision === 'reject' ? 'destructive' : 'default'} disabled={review.isPending}>
                {t(`documentRequests.review.${decision}Submit`)}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  )
}
