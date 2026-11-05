import { useCallback, useMemo, useState } from 'react'
import axios from 'axios'
import { useForm, type Path, type UseFormSetError } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/forbidden-message'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { buildEditContractSchema, type EditContractFormValues } from '@/features/contracts/contract-schema'
import { linesToFormValues, vatRatePercentsFromLines } from '@/features/quotes/quote-line-values'
import { toProductLineRows } from '@/features/request-management/request-work-payload'
import { buildUpdatePayload, useUpdateContract } from '@/features/contracts/use-contract-mutations'
import type { ContractDetail, ContractDetailWithPermissions } from '@/features/contracts/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['title', 'offer_lines', 'renewal_date', 'expiry_date', 'payment_notes', 'comments'] as const

/** `offer_lines.{i}` or `offer_lines.{i}.{field}`: a row-level refusal (spec 0360 D-4) or a field of the row. */
const OFFER_LINE_ERROR_KEY = /^offer_lines\.(\d+)(?:\.(\w+))?$/

/**
 * The API serializes the contract dates as full ISO timestamps
 * (`2026-09-20T00:00:00.000000Z`): a date input only reads the day part.
 */
function toDateInputValue(value: string | null): string | null {
  return value ? value.slice(0, 10) : null
}

/**
 * Maps the row-scoped 422 keys onto the row editor's fields. A refusal of the
 * whole row (no field in the key, e.g. a row locked by its work order) lands
 * on the product line, the one slot every card shows; `offer_lines` itself is
 * mapped by `applyServerValidationErrors`.
 */
function applyOfferLineErrors(error: unknown, setError: UseFormSetError<EditContractFormValues>): void {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return
  }
  const errors = error.response.data?.errors as Record<string, string[]> | undefined
  for (const [key, messages] of Object.entries(errors ?? {})) {
    const match = OFFER_LINE_ERROR_KEY.exec(key)
    if (match && messages[0]) {
      setError(`offer_lines.${match[1]}.${match[2] ?? 'product_id'}` as Path<EditContractFormValues>, {
        type: 'server',
        message: messages[0],
      })
    }
  }
}

/** The persisted contract as the edit form holds it (dates as the `YYYY-MM-DD` a date input reads). */
export function contractValues(contract: ContractDetail): EditContractFormValues {
  return {
    title: contract.quote.title,
    // Watched only: scopes the row editor's catalogue to the offer's categories.
    product_lines: toProductLineRows(contract.product_lines),
    offer_lines: linesToFormValues(contract.offer_lines, false),
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
  const { t, i18n } = useTranslation()
  const [editingField, setEditingField] = useState<string | null>(null)
  const values = useMemo(() => contractValues(contract), [contract])
  const schema = useMemo(() => buildEditContractSchema(t), [t])
  const form = useForm<EditContractFormValues>({
    resolver: zodResolver(schema),
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

  // The VAT-percent cache the rows' live preview reads, seeded from the saved rows (the picker never exposes a percentage).
  const [vatRatePercentById, setVatRatePercentById] = useState<Record<number, number>>(() =>
    vatRatePercentsFromLines(contract.offer_lines),
  )
  const rememberVatRatePercent = useCallback((vatRateId: number, percent: number) => {
    setVatRatePercentById((previous) =>
      previous[vatRateId] === percent ? previous : { ...previous, [vatRateId]: percent },
    )
  }, [])
  const vatRatePercentFor = useCallback((vatRateId: number) => vatRatePercentById[vatRateId] ?? null, [vatRatePercentById])

  const onSubmit = async (next: EditContractFormValues) => {
    try {
      await updateMutation.mutateAsync(buildUpdatePayload(next, values))
    } catch (error) {
      applyOfferLineErrors(error, form.setError)
      if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])) {
        toast.error(apiErrorMessage(error, t('contracts.actions.edit.genericError'), i18n))
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

  return { form, inline, vatRatePercentFor, rememberVatRatePercent }
}

/** Everything the detail's editable rows read: the form and the inline state. */
export type ContractDetailEditor = ReturnType<typeof useContractInlineEdit>
