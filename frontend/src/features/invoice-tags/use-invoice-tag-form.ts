import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createInvoiceTag, invoiceTagDetailQueryKey, updateInvoiceTag } from '@/features/invoice-tags/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/invoice-tags/invoice-tag-form-payload'
import {
  buildCreateInvoiceTagSchema,
  buildUpdateInvoiceTagSchema,
  type CreateInvoiceTagFormValues,
  type UpdateInvoiceTagFormValues,
} from '@/features/invoice-tags/invoice-tag-schema'
import type { InvoiceTagDetail, InvoiceTagFormMode } from '@/features/invoice-tags/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'name',
  'description',
  'color',
  'is_active',
] as const

export type InvoiceTagFormValues = CreateInvoiceTagFormValues & UpdateInvoiceTagFormValues

interface UseInvoiceTagFormArgs {
  mode: InvoiceTagFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (invoiceTag: InvoiceTagDetail) => void
}

/**
 * Owns every non-render concern of the invoice tag form: RHF/Zod wiring,
 * default values, server 422 mapping and the create/update submit. Create
 * backs `InvoiceTagForm`; edit backs the detail's in-place rows (diff PATCH). The component stays
 * UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useInvoiceTagForm({ mode, onSuccess }: UseInvoiceTagFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateInvoiceTagSchema(t) : buildCreateInvoiceTagSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<InvoiceTagFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.invoiceTag.name,
        description: mode.invoiceTag.description,
        color: mode.invoiceTag.color,
        is_active: mode.invoiceTag.is_active,
      }
    }
    return {
      name: '',
      description: null,
      color: '',
      is_active: true,
    }
  }, [mode])

  // Edit backs the detail's in-place rows: a saved record re-syncs the form,
  // `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<InvoiceTagFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: InvoiceTagFormValues) => {
    setServerError(null)
    const errorFields: Path<InvoiceTagFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateInvoiceTag(
          mode.invoiceTag.id,
          buildUpdatePayload(values, mode.invoiceTag),
        )
        queryClient.setQueryData(invoiceTagDetailQueryKey(mode.invoiceTag.id), saved)
        toast.success(t('invoiceTags.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createInvoiceTag(buildCreatePayload(values))
      toast.success(t('invoiceTags.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('invoiceTags.form.genericError'))
      }
    }
  }

  return {
    form,
    serverError,
    clearServerError: () => setServerError(null),
    onSubmit,
  }
}
