import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { toast } from 'sonner'
import type { TFunction } from 'i18next'
import { Check, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Textarea } from '@/components/ui/textarea'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import type { ApiErrorResponse } from '@/api/types'
import { approveFieldChangeRequest, rejectFieldChangeRequest } from '@/features/field-change-requests/api'
import { fieldChangeRequestKeys } from '@/features/field-change-requests/query-keys'
import {
  buildHandleFieldChangeRequestSchema,
  type HandleFieldChangeRequestFormValues,
} from '@/features/field-change-requests/field-change-request-schema'
import type { FieldChangeRequestResource } from '@/features/field-change-requests/types'

type HandleAction = 'approve' | 'reject'

const FORM_DEFAULT_VALUES: HandleFieldChangeRequestFormValues = { note: '' }

/** Detail layout: a full-width bar under the hero, separated from the sections below. */
const BAR_CLASS = 'flex flex-wrap items-center gap-2 border-b px-6 py-3'

interface FieldChangeRequestActionsProps {
  request: FieldChangeRequestResource
  /** Called with the freshly returned resource once approve/reject succeeds. */
  onHandled: (request: FieldChangeRequestResource) => void
  /**
   * Layout of the button row, so the same actions mount both as the detail's
   * own bar (default) and inline inside a record's change-request card, which
   * brings its own padding and border.
   */
  className?: string
}

/** The server's own message on failure, a generic fallback otherwise (AC-049: the 409 conflict shows it verbatim). */
function resolveHandleErrorMessage(error: unknown, t: TFunction): string {
  if (axios.isAxiosError<ApiErrorResponse>(error) && error.response?.data?.message) {
    return error.response.data.message
  }
  return t('fieldChangeRequests.detail.actions.genericError')
}

/**
 * Approve/Reject affordances of a request (spec 0078, AC-047), mounted both
 * by the detail view and by a record's own change-request card: each
 * button exists in the DOM only when its own `can.*` flag from the resource
 * is `true` — the UI hides, the backend authorizes. Confirming opens a small
 * dialog with an optional free-text note, mirroring the proposal dialog's
 * own `reason` field. On a 409 conflict (AC-049, the field changed since the
 * request was created) the server's message is shown as a toast and nothing
 * else happens: no local state changes, no refetch, no page reload — the
 * request stays visibly `pending`.
 */
export function FieldChangeRequestActions({
  request,
  onHandled,
  className = BAR_CLASS,
}: FieldChangeRequestActionsProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [openAction, setOpenAction] = useState<HandleAction | null>(null)
  const schema = buildHandleFieldChangeRequestSchema(t)

  const form = useForm<HandleFieldChangeRequestFormValues>({
    resolver: zodResolver(schema),
    defaultValues: FORM_DEFAULT_VALUES,
  })

  const close = () => {
    setOpenAction(null)
    form.reset(FORM_DEFAULT_VALUES)
  }

  const mutation = useMutation({
    mutationFn: (values: HandleFieldChangeRequestFormValues) => {
      const note = values.note || null
      return openAction === 'reject'
        ? rejectFieldChangeRequest(request.id, note)
        : approveFieldChangeRequest(request.id, note)
    },
    onSuccess: (updated) => {
      toast.success(
        openAction === 'reject'
          ? t('fieldChangeRequests.detail.actions.rejected')
          : t('fieldChangeRequests.detail.actions.approved'),
      )
      onHandled(updated)
      void queryClient.invalidateQueries({ queryKey: fieldChangeRequestKeys.all })
      close()
    },
    onError: (error) => {
      toast.error(resolveHandleErrorMessage(error, t))
    },
  })

  const onSubmit = (values: HandleFieldChangeRequestFormValues) => {
    mutation.mutate(values)
  }

  return (
    <div className={className}>
      {request.can.approve ? (
        <Button type="button" size="sm" onClick={() => setOpenAction('approve')}>
          <Check aria-hidden="true" />
          {t('fieldChangeRequests.detail.actions.approve')}
        </Button>
      ) : null}
      {request.can.reject ? (
        <Button type="button" variant="destructive" size="sm" onClick={() => setOpenAction('reject')}>
          <X aria-hidden="true" />
          {t('fieldChangeRequests.detail.actions.reject')}
        </Button>
      ) : null}

      <Dialog
        open={openAction !== null}
        onOpenChange={(open) => {
          if (!open) {
            close()
          }
        }}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {openAction === 'reject'
                ? t('fieldChangeRequests.detail.actions.rejectTitle')
                : t('fieldChangeRequests.detail.actions.approveTitle')}
            </DialogTitle>
            <DialogDescription>
              {openAction === 'reject'
                ? t('fieldChangeRequests.detail.actions.rejectDescription')
                : t('fieldChangeRequests.detail.actions.approveDescription')}
            </DialogDescription>
          </DialogHeader>

          <Form {...form}>
            <form
              id="field-change-request-handle-form"
              className="flex flex-col gap-3"
              onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}
            >
              <FormField
                control={form.control}
                name="note"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t('fieldChangeRequests.detail.actions.noteLabel')}</FormLabel>
                    <FormControl>
                      <Textarea
                        rows={3}
                        value={field.value ?? ''}
                        onChange={field.onChange}
                        onBlur={field.onBlur}
                        name={field.name}
                        ref={field.ref}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </form>
          </Form>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={close}>
              {t('common.cancel')}
            </Button>
            <Button type="submit" form="field-change-request-handle-form" disabled={mutation.isPending}>
              {mutation.isPending
                ? t('fieldChangeRequests.detail.actions.submitting')
                : t('fieldChangeRequests.detail.actions.confirm')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
