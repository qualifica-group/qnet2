import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { toast } from 'sonner'
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
import { createFieldChangeRequest } from '@/features/field-change-requests/api'
import { fieldChangeRequestKeys } from '@/features/field-change-requests/query-keys'
import {
  buildFieldChangeRequestSchema,
  type FieldChangeRequestFormValues,
} from '@/features/field-change-requests/field-change-request-schema'
import { FieldChangeRequestDialogContext } from '@/features/field-change-requests/use-field-change-request-dialog'
import type { RequestFieldChangeParams } from '@/features/field-change-requests/types'

const FORM_DEFAULT_VALUES: FieldChangeRequestFormValues = { reason: '' }

interface FieldChangeRequestDialogProps {
  /** The pending proposal to show, or `null` while closed. */
  prompt: RequestFieldChangeParams | null
  onClose: () => void
}

/** Resolves the toast message for a failed submission: the server's own message, a generic fallback otherwise. */
function resolveSubmitErrorMessage(error: unknown, t: TFunction): string {
  if (axios.isAxiosError<ApiErrorResponse>(error) && error.response?.data?.message) {
    return error.response.data.message
  }
  return t('fieldChangeRequests.dialog.genericError')
}

/**
 * Generic "propose a field change" dialog (spec 0078, architectural
 * constraint AC-054): parametrized entirely by `prompt`, it never imports or
 * references a specific resource or field — the Fonte/Gestione-Richieste use
 * case is just its first caller. Mounted once by
 * `FieldChangeRequestDialogProvider` and driven imperatively via
 * `useRequestFieldChange().requestFieldChange(...)`.
 */
export function FieldChangeRequestDialog({ prompt, onClose }: FieldChangeRequestDialogProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const schema = buildFieldChangeRequestSchema(t)

  const form = useForm<FieldChangeRequestFormValues>({
    resolver: zodResolver(schema),
    defaultValues: FORM_DEFAULT_VALUES,
  })

  // Step 1: reset the form whenever a new proposal opens, so a leftover
  // reason from a previous field never leaks into the next one.
  useEffect(() => {
    if (prompt) {
      form.reset(FORM_DEFAULT_VALUES)
    }
  }, [prompt, form])

  const mutation = useMutation({
    mutationFn: createFieldChangeRequest,
    onSuccess: () => {
      toast.success(t('fieldChangeRequests.dialog.success'))
      void queryClient.invalidateQueries({ queryKey: fieldChangeRequestKeys.all })
      onClose()
    },
    onError: (error) => {
      toast.error(resolveSubmitErrorMessage(error, t))
    },
  })

  // Step 2: on confirm, send only the frozen payload shape (resource,
  // subject_id, field, requested_value, reason) — never a client-side
  // snapshot of the current value (D-6).
  const onSubmit = (values: FieldChangeRequestFormValues) => {
    if (!prompt) {
      return
    }
    mutation.mutate({
      resource: prompt.resource,
      subject_id: prompt.subjectId,
      field: prompt.field,
      requested_value: prompt.requestedValue,
      reason: values.reason || null,
    })
  }

  const fieldLabel = prompt?.fieldLabelKey ? t(prompt.fieldLabelKey) : (prompt?.field ?? '')

  return (
    <Dialog
      open={prompt !== null}
      onOpenChange={(open) => {
        if (!open) {
          onClose()
        }
      }}
    >
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('fieldChangeRequests.dialog.title', { field: fieldLabel })}</DialogTitle>
          <DialogDescription>
            {t('fieldChangeRequests.dialog.description', { field: fieldLabel })}
          </DialogDescription>
        </DialogHeader>

        {prompt ? (
          <div className="flex flex-col gap-2 rounded-md border border-border bg-muted/40 px-3 py-2 text-sm">
            <div className="flex items-center justify-between gap-3">
              <span className="text-muted-foreground">
                {t('fieldChangeRequests.dialog.current')}
              </span>
              <span className="truncate font-medium">
                {prompt.currentLabel ?? t('fieldChangeRequests.dialog.emptyValue')}
              </span>
            </div>
            <div className="flex items-center justify-between gap-3">
              <span className="text-muted-foreground">
                {t('fieldChangeRequests.dialog.requested')}
              </span>
              <span className="truncate font-medium">
                {prompt.requestedLabel ?? t('fieldChangeRequests.dialog.emptyValue')}
              </span>
            </div>
          </div>
        ) : null}

        <Form {...form}>
          <form
            id="field-change-request-form"
            className="flex flex-col gap-3"
            onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}
          >
            <FormField
              control={form.control}
              name="reason"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>
                    {t('fieldChangeRequests.dialog.reasonLabel', { field: fieldLabel })}
                  </FormLabel>
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
          <Button type="button" variant="outline" onClick={onClose}>
            {t('fieldChangeRequests.dialog.cancel')}
          </Button>
          <Button type="submit" form="field-change-request-form" disabled={mutation.isPending}>
            {mutation.isPending
              ? t('fieldChangeRequests.dialog.submitting')
              : t('fieldChangeRequests.dialog.submit')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

/**
 * Owns the single application-wide "propose a field change" dialog (spec
 * 0078). Deliberately generic: it only forwards the `(resource, subjectId,
 * field, requestedValue)` tuple a caller hands it to
 * {@link FieldChangeRequestDialog} — it has no knowledge of the Fonte field
 * or Gestione Richieste (the spec's primary architectural constraint,
 * AC-054). Mounted once near the router root, mirroring
 * `UserDetailSheetProvider`.
 */
export function FieldChangeRequestDialogProvider({ children }: { children: ReactNode }) {
  const [prompt, setPrompt] = useState<RequestFieldChangeParams | null>(null)

  const requestFieldChange = useCallback((params: RequestFieldChangeParams) => {
    setPrompt(params)
  }, [])

  const close = useCallback(() => setPrompt(null), [])

  const value = useMemo(() => ({ requestFieldChange }), [requestFieldChange])

  return (
    <FieldChangeRequestDialogContext.Provider value={value}>
      {children}
      <FieldChangeRequestDialog prompt={prompt} onClose={close} />
    </FieldChangeRequestDialogContext.Provider>
  )
}
