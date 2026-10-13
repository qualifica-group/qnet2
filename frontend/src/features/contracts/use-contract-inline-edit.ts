import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { buildEditContractSchema, type EditContractFormValues } from '@/features/contracts/contract-schema'
import { buildUpdatePayload, useUpdateContract } from '@/features/contracts/use-contract-mutations'
import type { ContractDetail, ContractDetailWithPermissions } from '@/features/contracts/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['renewal_date', 'expiry_date', 'payment_notes', 'comments'] as const

/**
 * The API serializes the contract dates as full ISO timestamps
 * (`2026-09-20T00:00:00.000000Z`): a date input only reads the day part.
 */
function toDateInputValue(value: string | null): string | null {
  return value ? value.slice(0, 10) : null
}

/** The schema has no translated messages: one instance serves every contract. */
const EDIT_SCHEMA = buildEditContractSchema()

/** The persisted contract as the edit form holds it (dates as the `YYYY-MM-DD` a date input reads). */
export function contractValues(contract: ContractDetail): EditContractFormValues {
  return {
    renewal_date: toDateInputValue(contract.renewal_date),
    expiry_date: toDateInputValue(contract.expiry_date),
    payment_notes: contract.payment_notes,
    comments: contract.comments,
  }
}

/**
 * Drives the contract detail's in-place editing (spec 0219): ONE editor open
 * at a time over the PATCH subset, Confirm sending only what that editor
 * changed (`buildUpdatePayload`). The saved contract — with its refreshed
 * `permissions` — goes back to the host through `onChanged`.
 */
export function useContractInlineEdit(
  contract: ContractDetailWithPermissions,
  onChanged: (contract: ContractDetailWithPermissions) => void,
) {
  const { t } = useTranslation()
  const [editingField, setEditingField] = useState<string | null>(null)
  const values = useMemo(() => contractValues(contract), [contract])
  const form = useForm<EditContractFormValues>({
    resolver: zodResolver(EDIT_SCHEMA),
    defaultValues: values,
    values,
    resetOptions: { keepDirtyValues: true },
  })

  const updateMutation = useUpdateContract({
    contractId: contract.id,
    onSuccess: (updated) => {
      toast.success(t('contracts.actions.edit.success'))
      setEditingField(null)
      onChanged({ ...contract, ...updated })
    },
  })

  // `keepDirtyValues: false`: the background re-sync keeps dirty values, but
  // an unconfirmed edit must really go.
  const discard = () => form.reset(undefined, { keepDirtyValues: false })

  const start = (field: string) => {
    discard()
    setEditingField(field)
  }

  const cancel = () => {
    discard()
    setEditingField(null)
  }

  const onSubmit = async (next: EditContractFormValues) => {
    try {
      await updateMutation.mutateAsync(buildUpdatePayload(next, values))
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])) {
        toast.error(t('contracts.actions.edit.genericError'))
      }
    }
  }

  const inline: InlineEdit = {
    editingField,
    start,
    cancel,
    save: () => void form.handleSubmit(onSubmit)(),
    dismiss: cancel,
    isSaving: updateMutation.isPending,
    error: null,
    confirmLabel: t('common.inlineEdit.save'),
    cancelLabel: t('common.inlineEdit.cancel'),
  }

  return { form, inline }
}

/** Everything the detail's editable rows read: the form and the inline state. */
export type ContractDetailEditor = ReturnType<typeof useContractInlineEdit>
