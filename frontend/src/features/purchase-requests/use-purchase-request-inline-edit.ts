import { useMemo, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { get, type FieldErrors } from 'react-hook-form'
import { firstErrorMessage } from '@/components/record-form/first-error-message'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { purchaseRequestKeys, updatePurchaseRequest } from '@/features/purchase-requests/api'
import {
  buildHeaderPatch,
  buildLinesPayload,
  toFormValues,
} from '@/features/purchase-requests/purchase-request-payload'
import type { PurchaseRequestFormValues } from '@/features/purchase-requests/purchase-request-schema'
import {
  applyServerErrors,
  isConflict,
  serverMessage,
} from '@/features/purchase-requests/purchase-request-server-errors'
import { usePurchaseRequestFormState } from '@/features/purchase-requests/use-purchase-request-form'
import type { PurchaseRequest, PurchaseRequestPayload } from '@/features/purchase-requests/types'

/** Prefix of the editing key of one line: `lines.<index>`. */
const LINE_FIELD_PREFIX = 'lines.'

/**
 * Rows whose editor shows more than their own field (spec 0256 D-6): a rule
 * failing on one of these is visible, anything else needs a toast.
 */
const EDITOR_EXTRA_FIELDS: Record<string, string[]> = {
  company_id: ['company_site_id'],
  requester_id: ['function_manager_id'],
  business_function_id: ['function_manager_id'],
}

export function lineEditingKey(index: number): string {
  return `${LINE_FIELD_PREFIX}${index}`
}

/**
 * Drives the RDA detail's in-place editing (spec 0256 D-6/D-7): ONE editor
 * open at a time, a header row or a line. Confirm validates the whole form
 * and PATCHes only what that editor changed: the header keys that differ from
 * the saved RDA, or the whole `lines` set when a line was edited. A success
 * resets the form on the saved RDA; Cancel just puts the saved values back.
 *
 * Opening another editor first discards the unconfirmed one, so an edit is
 * never carried silently into a different save.
 */
export function usePurchaseRequestInlineEdit(request: PurchaseRequest, onChanged?: () => void) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [editingField, setEditingField] = useState<string | null>(null)
  const [serverError, setServerError] = useState<string | null>(null)
  const persisted = useMemo(() => toFormValues(request), [request])
  const state = usePurchaseRequestFormState({ request, requesterId: request.requester.id })
  const { form, addLine } = state

  const mutation = useMutation<PurchaseRequest, unknown, Partial<PurchaseRequestPayload>>({
    mutationFn: (payload) => updatePurchaseRequest(request.id, payload),
    onSuccess: (saved) => {
      queryClient.setQueryData(purchaseRequestKeys.detail(saved.id), saved)
      form.reset(toFormValues(saved))
      setEditingField(null)
      toast.success(t('purchaseRequests.messages.updated'))
      onChanged?.()
    },
    onError: (error) => {
      const unmapped = applyServerErrors(error, form.setError)
      if (unmapped !== null) {
        setServerError(unmapped.length > 0 ? unmapped.join(' ') : null)
        return
      }
      setServerError(
        serverMessage(error) ??
          t(isConflict(error) ? 'purchaseRequests.messages.conflict' : 'purchaseRequests.messages.saveError'),
      )
    },
  })

  const discard = () => {
    form.reset(persisted)
    setServerError(null)
  }

  const start = (field: string) => {
    discard()
    setEditingField(field)
  }

  const cancel = () => {
    discard()
    setEditingField(null)
  }

  /** Opens a new, empty line in edit mode at the end of the list. */
  const startNewLine = () => {
    discard()
    addLine()
    setEditingField(lineEditingKey(persisted.lines.length))
  }

  // A rule failing on a field the open editor does not show has no message on screen: say it.
  const handleInvalid = (errors: FieldErrors<PurchaseRequestFormValues>) => {
    const shown = editingField === null ? [] : [editingField, ...(EDITOR_EXTRA_FIELDS[editingField] ?? [])]
    if (!shown.some((field) => get(errors, field) !== undefined)) {
      toast.error(firstErrorMessage(errors) ?? t('purchaseRequests.messages.saveError'))
    }
  }

  const save = () => {
    void form.handleSubmit((values) => {
      setServerError(null)
      const payload = editingField?.startsWith(LINE_FIELD_PREFIX)
        ? { lines: buildLinesPayload(values.lines) }
        : buildHeaderPatch(values, persisted)
      if (Object.keys(payload).length === 0) {
        cancel()
        return
      }
      mutation.mutate(payload)
    }, handleInvalid)()
  }

  /** Deletes a saved line right away (the caller has already asked for confirmation). */
  const removeLine = (index: number) => {
    discard()
    setEditingField(null)
    mutation.mutate({ lines: buildLinesPayload(persisted.lines.filter((_, position) => position !== index)) })
  }

  const inline: InlineEdit = {
    editingField,
    start,
    cancel,
    save,
    dismiss: cancel,
    isSaving: mutation.isPending,
    error: serverError,
    confirmLabel: t('common.inlineEdit.save'),
    cancelLabel: t('common.inlineEdit.cancel'),
  }

  return { ...state, inline, startNewLine, removeLine }
}

/** Everything the detail's editable sections read: the form state, the inline state and the line actions. */
export type PurchaseRequestDetailEditor = ReturnType<typeof usePurchaseRequestInlineEdit>
