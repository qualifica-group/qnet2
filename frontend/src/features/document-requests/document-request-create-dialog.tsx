import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { toast } from 'sonner'
import { AsyncPaginatedSelect, type AsyncPaginatedSelectLabels } from '@/components/ui/async-paginated-select'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Skeleton } from '@/components/ui/skeleton'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { DOCUMENT_CHECKLIST_CATEGORIES_FOR_SELECT_RESOURCE } from '@/features/document-checklist-categories/api'
import { DOCUMENT_CHECKLISTS_FOR_SELECT_RESOURCE } from '@/features/document-requests/api'
import { DocumentRequestFields } from '@/features/document-requests/document-request-fields'
import {
  buildCreateDocumentRequestSchema,
  toCreateDocumentRequestPayload,
  type DocumentRequestFormValues,
} from '@/features/document-requests/document-request-schema'
import { useCreateDocumentRequest, useDocumentRequestDefaults } from '@/features/document-requests/use-document-requests'

const SERVER_FIELDS: (keyof DocumentRequestFormValues)[] = ['document_checklist_id', 'due_date', 'recipient_email', 'message']

interface DocumentRequestCreateDialogProps {
  workOrderId: number
  open: boolean
  onOpenChange: (open: boolean) => void
}

interface CreateFormProps {
  workOrderId: number
  recipientEmail: string
  onClose: () => void
}

function CreateForm({ workOrderId, recipientEmail, onClose }: CreateFormProps) {
  const { t } = useTranslation()
  const create = useCreateDocumentRequest(workOrderId)
  const [categoryId, setCategoryId] = useState<number | null>(null)
  const form = useForm<DocumentRequestFormValues>({
    resolver: zodResolver(buildCreateDocumentRequestSchema(t)),
    defaultValues: { document_checklist_id: null, due_date: '', recipient_email: recipientEmail, message: '', send: true },
  })

  const selectLabels = (key: 'category' | 'checklist'): AsyncPaginatedSelectLabels => ({
    placeholder: t(`documentRequests.form.${key}Placeholder`),
    searchPlaceholder: t(`documentRequests.form.${key}Search`),
    empty: t(`documentRequests.form.${key}Empty`),
    error: t(`documentRequests.form.${key}Error`),
    clearLabel: t('common.clear'),
    triggerLabel: t(`documentRequests.form.${key}`),
    retry: t('common.retry'),
  })

  function onSubmit(values: DocumentRequestFormValues) {
    create.mutate(toCreateDocumentRequestPayload(values), {
      onSuccess: () => {
        toast.success(t('documentRequests.create.success'))
        onClose()
      },
      onError: (error) => {
        if (!applyServerValidationErrors(error, form.setError, SERVER_FIELDS)) {
          toast.error(t('documentRequests.create.error'))
        }
      },
    })
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-3" noValidate>
        <div className="flex flex-col gap-1">
          <span className="text-sm font-medium">{t('documentRequests.form.category')}</span>
          <AsyncPaginatedSelect
            resource={DOCUMENT_CHECKLIST_CATEGORIES_FOR_SELECT_RESOURCE}
            value={categoryId}
            onChange={(next) => {
              setCategoryId(next)
              form.setValue('document_checklist_id', null)
            }}
            labels={selectLabels('category')}
          />
        </div>
        <FormField
          control={form.control}
          name="document_checklist_id"
          render={({ field }) => (
            <FormItem className="gap-1">
              <FormLabel>{t('documentRequests.form.checklist')}</FormLabel>
              <FormControl>
                <AsyncPaginatedSelect
                  key={categoryId ?? 'all'}
                  resource={DOCUMENT_CHECKLISTS_FOR_SELECT_RESOURCE}
                  params={categoryId === null ? undefined : { category_id: categoryId }}
                  value={field.value}
                  onChange={field.onChange}
                  labels={selectLabels('checklist')}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <DocumentRequestFields />
        <DialogFooter>
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" disabled={create.isPending}>
            {t(create.isPending ? 'documentRequests.create.submitting' : 'documentRequests.create.submit')}
          </Button>
        </DialogFooter>
      </form>
    </Form>
  )
}

/** "Richiedi documenti" (spec 0428 AC-018): selectable checklists only, email pre-filled from the customer. */
export function DocumentRequestCreateDialog({ workOrderId, open, onOpenChange }: DocumentRequestCreateDialogProps) {
  const { t } = useTranslation()
  const defaults = useDocumentRequestDefaults(workOrderId, open)
  // A failed defaults lookup must not block the request: the recipient is optional.
  const ready = defaults.isSuccess || defaults.isError

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{t('documentRequests.create.title')}</DialogTitle>
          <DialogDescription>
            {defaults.data?.customer_name
              ? t('documentRequests.create.descriptionCustomer', { customer: defaults.data.customer_name })
              : t('documentRequests.create.description')}
          </DialogDescription>
        </DialogHeader>
        {ready ? (
          <CreateForm
            workOrderId={workOrderId}
            recipientEmail={defaults.data?.recipient_email ?? ''}
            onClose={() => onOpenChange(false)}
          />
        ) : (
          <Skeleton className="h-40 w-full" />
        )}
      </DialogContent>
    </Dialog>
  )
}
