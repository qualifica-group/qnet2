import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { get, type FieldErrors } from 'react-hook-form'
import { firstErrorMessage } from '@/components/record-form/first-error-message'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { useProductForm, type ProductFormValues } from '@/features/products/use-product-form'
import { useProductFormSubmit } from '@/features/products/use-product-form-submit'
import type { ProductDetailWithPermissions, ProductFormMode } from '@/features/products/types'

/**
 * Drives the product detail's in-place editing (spec 0218) on top of the
 * edit-mode `useProductForm`: ONE editor open at a time, and Confirm submits
 * the whole form, whose diff-based PATCH (`buildUpdatePayload`) sends only what
 * that editor changed — a category change with the attributes it brings in
 * included (D-4). A successful save resets the form on the saved record.
 *
 * Opening another row first discards the unconfirmed one, the attribute set
 * included (`syncCategory`): an abandoned category must not keep its
 * required attributes in the next row's validation.
 */
export function useProductInlineEdit(product: ProductDetailWithPermissions, onChanged?: () => void) {
  const { t } = useTranslation()
  const [editingField, setEditingField] = useState<string | null>(null)
  // Stable per persisted record: `useProductForm` re-syncs its values on a new one.
  const mode = useMemo<ProductFormMode>(() => ({ type: 'edit', product }), [product])

  const state = useProductForm({ mode })
  const { form } = state
  const submit = useProductFormSubmit({
    state,
    mode,
    onSuccess: () => {
      setEditingField(null)
      onChanged?.()
    },
  })

  // `keepDirtyValues: false`: the form's `resetOptions` keep dirty values for
  // the background re-sync, but here the unconfirmed edit must really go.
  const discard = () => {
    form.reset(undefined, { keepDirtyValues: false })
    state.syncCategory()
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
  const handleInvalid = (errors: FieldErrors<ProductFormValues>) => {
    if (editingField === null || get(errors, editingField) === undefined) {
      toast.error(firstErrorMessage(errors) ?? t('products.form.genericError'))
    }
  }

  const save = () => {
    void form.handleSubmit(submit.onSubmit, handleInvalid)()
  }

  const inline: InlineEdit = {
    editingField,
    start,
    cancel,
    save,
    dismiss: cancel,
    isSaving: form.formState.isSubmitting,
    error: submit.serverError,
    confirmLabel: t('common.inlineEdit.save'),
    cancelLabel: t('common.inlineEdit.cancel'),
  }

  return { state, inline }
}

/** Everything the detail's editable sections read: the form state and the inline state. */
export type ProductDetailEditor = ReturnType<typeof useProductInlineEdit>
