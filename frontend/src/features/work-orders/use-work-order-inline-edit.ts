import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { get, type FieldErrors } from 'react-hook-form'
import { firstErrorMessage } from '@/components/record-form/first-error-message'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { useWorkOrderForm, type WorkOrderFormValues } from '@/features/work-orders/use-work-order-form'
import type { WorkOrderDetailWithPermissions, WorkOrderFormMode } from '@/features/work-orders/types'

/**
 * Drives the work order detail's in-place editing (spec 0195 applied to
 * Commesse, user directive 2026-10-06) on top of the edit-mode
 * `useWorkOrderForm`: ONE editor open at a time, and Confirm submits the whole
 * form, whose diff-based PATCH (`buildUpdatePayload`) sends only what that
 * editor changed. A successful save resets
 * the form on the saved record and closes the editor.
 *
 * Opening another row first discards the unconfirmed one: an edit is never
 * carried silently into a different field's save.
 */
export function useWorkOrderInlineEdit(workOrder: WorkOrderDetailWithPermissions, onChanged?: () => void) {
  const { t } = useTranslation()
  const [editingField, setEditingField] = useState<string | null>(null)
  // Stable per persisted record: `useWorkOrderForm` re-syncs its values on a new one.
  const mode = useMemo<WorkOrderFormMode>(() => ({ type: 'edit', workOrder }), [workOrder])

  const workOrderForm = useWorkOrderForm({
    mode,
    onSuccess: () => {
      setEditingField(null)
      onChanged?.()
    },
  })
  const { form, onSubmit, serverError, clearServerError } = workOrderForm

  // `keepDirtyValues: false`: the form's `resetOptions` keep dirty values for
  // the background re-sync, but here the unconfirmed edit must really go.
  const start = (field: string) => {
    form.reset(undefined, { keepDirtyValues: false })
    clearServerError()
    setEditingField(field)
  }

  const cancel = () => {
    form.reset(undefined, { keepDirtyValues: false })
    clearServerError()
    setEditingField(null)
  }

  // A rule failing on a field the open editor does not show (e.g. a required
  // Attribute a historical record left empty) has no message on screen: say it.
  const handleInvalid = (errors: FieldErrors<WorkOrderFormValues>) => {
    if (editingField === null || get(errors, editingField) === undefined) {
      toast.error(firstErrorMessage(errors) ?? t('workOrders.form.genericError'))
    }
  }

  const save = () => {
    void form.handleSubmit(onSubmit, handleInvalid)()
  }

  const inline: InlineEdit = {
    editingField,
    start,
    cancel,
    save,
    dismiss: cancel,
    isSaving: form.formState.isSubmitting,
    error: serverError,
    confirmLabel: t('common.inlineEdit.save'),
    cancelLabel: t('common.inlineEdit.cancel'),
  }

  return { ...workOrderForm, inline }
}

/** Everything the detail's editable sections read: the form, its cascade handlers and the inline state. */
export type WorkOrderDetailEditor = ReturnType<typeof useWorkOrderInlineEdit>
