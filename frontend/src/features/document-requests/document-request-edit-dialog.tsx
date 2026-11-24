import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form } from '@/components/ui/form'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { DocumentRequestFields } from '@/features/document-requests/document-request-fields'
import {
  buildEditDocumentRequestSchema,
  toUpdateDocumentRequestPayload,
  type DocumentRequestFormValues,
} from '@/features/document-requests/document-request-schema'
import type { DocumentRequest } from '@/features/document-requests/types'
import { useUpdateDocumentRequest } from '@/features/document-requests/use-document-requests'

const SERVER_FIELDS: (keyof DocumentRequestFormValues)[] = ['due_date', 'recipient_email', 'message']

interface DocumentRequestEditDialogProps {
  request: DocumentRequest
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** Edits due date, recipient and message of a request (the checklist never changes, spec 0428 D-8). */
export function DocumentRequestEditDialog({ request, open, onOpenChange }: DocumentRequestEditDialogProps) {
  const { t } = useTranslation()
  const update = useUpdateDocumentRequest(request.work_order_id, request.id)
  const form = useForm<DocumentRequestFormValues>({
    resolver: zodResolver(buildEditDocumentRequestSchema(t)),
    defaultValues: {
      document_checklist_id: request.checklist.id,
      due_date: request.due_date ?? '',
      recipient_email: request.recipient_email ?? '',
      message: request.message ?? '',
      send: true,
    },
  })

  function onSubmit(values: DocumentRequestFormValues) {
    update.mutate(toUpdateDocumentRequestPayload(values), {
      onSuccess: () => {
        toast.success(t('documentRequests.edit.success'))
        onOpenChange(false)
      },
      onError: (error) => {
        if (!applyServerValidationErrors(error, form.setError, SERVER_FIELDS)) {
          toast.error(t('documentRequests.edit.error'))
        }
      },
    })
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{t('documentRequests.edit.title')}</DialogTitle>
          <DialogDescription>{request.checklist.name}</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-3" noValidate>
            <DocumentRequestFields />
            <DialogFooter>
              <Button type="button" variant="secondary" onClick={() => onOpenChange(false)}>
                {t('common.cancel')}
              </Button>
              <Button type="submit" disabled={update.isPending}>
                {t(update.isPending ? 'documentRequests.edit.submitting' : 'documentRequests.edit.submit')}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  )
}
