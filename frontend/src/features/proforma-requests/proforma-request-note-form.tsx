import { useTranslation } from 'react-i18next'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { proformaRequestDetailQueryKey, updateProformaRequest } from '@/features/proforma-requests/api'
import { ProformaNoteField } from '@/features/proforma-requests/proforma-note-field'
import { useProformaNoteForm } from '@/features/proforma-requests/use-proforma-note-form'
import type { ProformaNoteFormValues } from '@/features/proforma-requests/proforma-note-schema'
import type { ProformaRequest } from '@/features/proforma-requests/types'

interface ProformaRequestNoteFormProps {
  proformaRequest: ProformaRequest
  onSuccess: (saved: ProformaRequest) => void
  onCancel: () => void
}

/** Edit form of a proforma request: the note is the only writable field. */
export function ProformaRequestNoteForm({ proformaRequest, onSuccess, onCancel }: ProformaRequestNoteFormProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const form = useProformaNoteForm(proformaRequest.note)

  const mutation = useMutation({
    mutationFn: (values: ProformaNoteFormValues) => updateProformaRequest(proformaRequest.id, values),
    onSuccess: (saved) => {
      void queryClient.invalidateQueries({ queryKey: proformaRequestDetailQueryKey(saved.id) })
      toast.success(t('proformaRequests.form.updated'))
      onSuccess(saved)
    },
    onError: (error) => {
      if (!applyServerValidationErrors(error, form.setError, ['note'])) {
        toast.error(t('proformaRequests.form.genericError'))
      }
    },
  })

  return (
    <Form {...form}>
      <form
        onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
        noValidate
        className="flex flex-col gap-4 p-4"
      >
        <p className="text-xs text-muted-foreground">
          {t('proformaRequests.form.workOrderLine', { code: proformaRequest.work_order.code })}
        </p>
        <ProformaNoteField control={form.control} />
        <div className="flex justify-end gap-2">
          <Button type="button" variant="secondary" size="sm" onClick={onCancel}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" size="sm" disabled={mutation.isPending}>
            {t('proformaRequests.form.save')}
          </Button>
        </div>
      </form>
    </Form>
  )
}
