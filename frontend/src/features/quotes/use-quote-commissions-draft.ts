import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { useConfirm } from '@/components/confirm-dialog-context'
import { useResourcePermissions } from '@/features/authorization/permissions'
import type { FieldPermission } from '@/features/authorization/types'
import type { CommissionRole } from '@/features/commission-configurations/types'
import { calculateCommissionAmount, calculateCommissionBaseNet, roundCommission } from './commission-calculator'
import {
  fetchQuoteCommissionDefaults,
  fetchQuoteCommissionRecipients,
  quoteCommissionDefaultsQueryKey,
  quoteCommissionRecipientsQueryKey,
} from './api'
import type {
  QuoteCommissionContext,
  QuoteCommissionRecipient,
  QuoteLineCommission,
  QuoteLineCommissionInput,
} from './types'

const LOOKUP_STALE_TIME_MS = 60_000

export interface QuoteCommissionsDraftInput {
  open: boolean
  disabled: boolean
  productId: number | null
  quantity: number | null
  unitPrice: number | null
  allocatedCostNet?: number
  commissions: QuoteLineCommissionInput[]
  commissionContext?: QuoteCommissionContext
}

/** The per-field permissions the dialog's controls honour. */
export interface QuoteCommissionFieldPermissions {
  recipient: FieldPermission
  type: FieldPermission
  value: FieldPermission
  note: FieldPermission
}

/**
 * State and lookups of the per-line commissions dialog. The recipient of each
 * role is NOT a choice: it is whoever was selected upstream (the quote's
 * commerciale/segnalatore/supervisore, the product's fornitore), resolved
 * server-side. A role with nobody selected upstream cannot be commissioned —
 * no add action, and an already-drafted one blocks the save rather than
 * failing at the API (`ValidatesQuoteLines` enforces the same rule).
 */
export function useQuoteCommissionsDraft(input: QuoteCommissionsDraftInput) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const confirm = useConfirm()
  const [draft, setDraft] = useState(input.commissions)

  // Step 1: the base every PERCENTAGE commission applies to (spec 0145
  // D-1/D-2) — the SAME rule as the live summary, never negative.
  const lineNet = roundCommission((input.quantity ?? 0) * (input.unitPrice ?? 0))
  const allocatedCostNet = input.allocatedCostNet ?? 0
  const commissionBase = calculateCommissionBaseNet(lineNet, allocatedCostNet)

  // Step 2: permissions.
  const collectionPermission = fieldPermission('commissions')
  const canMutate = !input.disabled && collectionPermission.visible && collectionPermission.editable
  const fields: QuoteCommissionFieldPermissions = {
    recipient: fieldPermission('commission_recipient'),
    type: fieldPermission('commission_type'),
    value: fieldPermission('commission_value'),
    note: fieldPermission('commission_internal_note'),
  }

  // Step 3: the locked recipients and the rules' own commissions. Both
  // endpoints demand quote create/update, so a read-only dialog asks neither.
  const recipientsPayload = {
    ...(input.commissionContext?.quoteId ? { quote_id: input.commissionContext.quoteId } : {}),
    product_id: input.productId ?? 0,
    commercial_id: input.commissionContext?.commercialId ?? null,
    reporter_id: input.commissionContext?.reporterId ?? null,
    supervisor_id: input.commissionContext?.supervisorId ?? null,
  }
  const recipientsQuery = useQuery({
    queryKey: quoteCommissionRecipientsQueryKey(recipientsPayload),
    queryFn: () => fetchQuoteCommissionRecipients(recipientsPayload),
    enabled: input.open && !input.disabled && input.productId !== null && fields.recipient.visible,
    staleTime: LOOKUP_STALE_TIME_MS,
  })
  const defaultsPayload = { ...recipientsPayload, line_net_amount: lineNet }
  const defaultsQuery = useQuery({
    queryKey: quoteCommissionDefaultsQueryKey(defaultsPayload),
    queryFn: () => fetchQuoteCommissionDefaults(defaultsPayload),
    enabled: input.open && canMutate && input.productId !== null,
    staleTime: LOOKUP_STALE_TIME_MS,
  })

  const recipients = recipientsQuery.data
  /** Until the lock resolves, no role is declared unavailable — only not yet addable. */
  const recipientsPending = recipients === undefined
  const recipientFor = (role: CommissionRole): QuoteCommissionRecipient | null => recipients?.[role] ?? null
  const systemDefaultsLoaded = defaultsQuery.data !== undefined
  const systemDefaultFor = (role: CommissionRole): QuoteLineCommission | null =>
    defaultsQuery.data?.commissions.find((item) => item.recipient_role === role) ?? null

  const byRole = useMemo(() => new Map(draft.map((item) => [item.recipient_role, item])), [draft])
  const amountOf = (commission: QuoteLineCommissionInput) =>
    calculateCommissionAmount(commission.commission_type, commission.value, commissionBase)
  const totalAmount = roundCommission(draft.reduce((sum, commission) => sum + amountOf(commission), 0))

  // Step 4: draft mutations. Any hand edit turns the row into an override.
  const patch = (role: CommissionRole, next: Partial<QuoteLineCommissionInput>) =>
    setDraft((current) =>
      current.map((item) =>
        item.recipient_role === role ? { ...item, ...next, origin: 'MANUAL_OVERRIDE' as const } : item,
      ),
    )
  const add = (role: CommissionRole, recipient: QuoteCommissionRecipient) =>
    setDraft((current) => [...current, {
      recipient_role: role,
      recipient_type: recipient.type,
      recipient_id: recipient.id,
      recipient: { id: recipient.id, name: recipient.name },
      commission_type: 'PERCENTAGE',
      value: 0,
      internal_note: null,
      origin: 'MANUAL_OVERRIDE',
      commission_configuration_id: null,
    }])
  /** Drops the override: the role goes back to the rules' own commission, keeping its persisted id and note. */
  const applySystemDefault = (systemDefault: QuoteLineCommission) =>
    setDraft((current) => {
      const existing = current.find((item) => item.recipient_role === systemDefault.recipient_role)
      const next: QuoteLineCommissionInput = {
        id: existing?.id,
        recipient_role: systemDefault.recipient_role,
        recipient_type: systemDefault.recipient_type,
        recipient_id: systemDefault.recipient_id,
        recipient: systemDefault.recipient,
        commission_type: systemDefault.commission_type,
        value: Number(systemDefault.value),
        internal_note: existing?.internal_note ?? systemDefault.internal_note,
        origin: systemDefault.origin,
        commission_configuration_id: systemDefault.commission_configuration_id,
      }
      return existing ? current.map((item) => (item === existing ? next : item)) : [...current, next]
    })
  const remove = async (role: CommissionRole) => {
    if (byRole.get(role)?.id !== undefined) {
      const accepted = await confirm({
        tone: 'destructive',
        title: t('quotes.form.commissions.removeTitle'),
        description: t('quotes.form.commissions.removeDescription'),
        confirmLabel: t('quotes.form.commissions.removeConfirm'),
        cancelLabel: t('common.cancel'),
      })
      if (!accepted) return
    }
    setDraft((current) => current.filter((item) => item.recipient_role !== role))
  }

  // Step 5: a drafted role whose recipient disappeared upstream would be a
  // guaranteed 422 — block the save so the user removes it first.
  const canSave =
    draft.every((item) => item.recipient_id > 0)
    && (recipientsPending || draft.every((item) => recipientFor(item.recipient_role) !== null))

  return {
    draft,
    byRole,
    lineNet,
    allocatedCostNet,
    commissionBase,
    totalAmount,
    amountOf,
    canMutate,
    canSave,
    fields,
    recipientsPending,
    recipientFor,
    systemDefaultsLoaded,
    systemDefaultFor,
    patch,
    add,
    applySystemDefault,
    remove,
  }
}

export type QuoteCommissionsDraft = ReturnType<typeof useQuoteCommissionsDraft>
