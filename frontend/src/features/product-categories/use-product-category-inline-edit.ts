import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { get, type FieldErrors } from 'react-hook-form'
import { firstErrorMessage } from '@/components/record-form/first-error-message'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type {
  ProductCategoryDetailWithPermissions,
  ProductCategoryFormMode,
} from '@/features/product-categories/types'
import { useBusinessFunctionResetConfirmation } from '@/features/product-categories/use-business-function-reset-confirmation'
import {
  useProductCategoryForm,
  type ProductCategoryFormValues,
} from '@/features/product-categories/use-product-category-form'
import { useProductCategoryFormSubmit } from '@/features/product-categories/use-product-category-form-submit'

/**
 * Drives the category detail's in-place editing (spec 0229) on top of the
 * edit-mode `useProductCategoryForm`: ONE editor open at a time, and Confirm
 * submits the whole form, whose diff-based PATCH (`buildUpdatePayload`) sends
 * only what that editor changed. A successful save resets the form on the
 * saved record. Assigning a business function that would wipe the ones the
 * descendants own is held back behind the reset confirmation dialog.
 *
 * Opening another row first discards the unconfirmed one: an edit is never
 * carried silently into a different field's save.
 */
export function useProductCategoryInlineEdit(category: ProductCategoryDetailWithPermissions, onChanged?: () => void) {
  const { t } = useTranslation()
  const [editingField, setEditingField] = useState<string | null>(null)
  // Stable per persisted record: `useProductCategoryForm` re-syncs its values on a new one.
  const mode = useMemo<ProductCategoryFormMode>(() => ({ type: 'edit', category }), [category])

  const { form, customFieldErrorPaths } = useProductCategoryForm({ mode })
  const submit = useProductCategoryFormSubmit({
    form,
    mode,
    customFieldErrorPaths,
    onSuccess: () => {
      setEditingField(null)
      onChanged?.()
    },
  })
  const businessFunctionReset = useBusinessFunctionResetConfirmation({ mode, onSubmit: submit.onSubmit })

  // `keepDirtyValues: false`: the form's `resetOptions` keep dirty values for
  // the background re-sync, but here the unconfirmed edit must really go.
  const discard = () => {
    form.reset(undefined, { keepDirtyValues: false })
    submit.clearServerError()
  }

  const start = (field: string) => {
    discard()
    setEditingField(field)
  }

  const cancel = () => {
    discard()
    setEditingField(null)
  }

  // A rule failing on a field the open editor does not show has no message on screen: say it.
  const handleInvalid = (errors: FieldErrors<ProductCategoryFormValues>) => {
    const own = editingField === null ? undefined : get(errors, editingField)
    if (typeof own?.message !== 'string') {
      toast.error(firstErrorMessage(errors) ?? t('productCategories.form.genericError'))
    }
  }

  const save = () => {
    void form.handleSubmit(businessFunctionReset.submit, handleInvalid)()
  }

  const inline: InlineEdit = {
    editingField,
    start,
    cancel,
    save,
    dismiss: cancel,
    // The reset dialog sits outside the open row: a press on it must not read as a dismiss.
    isSaving: form.formState.isSubmitting || businessFunctionReset.pendingCategories !== null || businessFunctionReset.isConfirming,
    error: submit.serverError,
    confirmLabel: t('common.inlineEdit.save'),
    cancelLabel: t('common.inlineEdit.cancel'),
  }

  return { form, inline, mode, businessFunctionReset }
}

/** Everything the detail's editable sections read: the form, the inline state and the edit mode. */
export type ProductCategoryDetailEditor = ReturnType<typeof useProductCategoryInlineEdit>
