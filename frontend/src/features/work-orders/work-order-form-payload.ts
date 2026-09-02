import { managerSlotsFromRefs, sameIdSet, sameManagerSlots } from '@/lib/utils'
import type {
  CreateWorkOrderPayload,
  UpdateWorkOrderPayload,
  WorkOrderDetail,
} from '@/features/work-orders/types'
import type { WorkOrderFormValues } from '@/features/work-orders/use-work-order-form'

/**
 * Builds the create payload. `code` is included only when set (trimmed,
 * non-empty) — an empty value falls back to server-side sequential
 * generation (D-1, mirrors `quotes`' `buildCreatePayload`). `force_close_reason`
 * is only ever sent when `is_force_closed` is true, mirroring the backend's
 * own D-4 "azzerato a NULL quando torna false" rule.
 */
export function buildCreatePayload(values: WorkOrderFormValues): CreateWorkOrderPayload {
  const code = values.code.trim()
  return {
    ...(code ? { code } : {}),
    quote_id: values.quote_id as number,
    title: values.title,
    type: values.type,
    start_date: values.start_date,
    supervisor_ids: values.supervisor_ids,
    participant_slots: values.participant_slots,
    callback_date: values.callback_date,
    description: values.description,
    internal_notes: values.internal_notes,
    is_force_closed: values.is_force_closed,
    force_close_reason: values.is_force_closed ? values.force_close_reason : null,
    quote_line_ids: values.quote_line_ids,
  }
}

/**
 * Builds a partial PATCH payload carrying only the fields that actually
 * changed from the original work order (AC-077). `code`/`quote_id` are NEVER
 * included (D-1/D-5): both are immutable after create for every role, and
 * the backend 422s on their mere presence, even when the value is unchanged.
 * `quote_line_ids` is sent only when the selected set differs from the
 * persisted one (AC-026: an untouched selection must not resend a no-op sync);
 * `supervisor_ids` and `participant_slots` follow the same rule (spec 0096,
 * AC-024/AC-073) — the latter compared POSITIONALLY, since a reorder is a
 * real change.
 */
export function buildUpdatePayload(
  values: WorkOrderFormValues,
  original: WorkOrderDetail,
): UpdateWorkOrderPayload {
  const payload: UpdateWorkOrderPayload = {}

  if (values.title !== original.title) {
    payload.title = values.title
  }
  if (values.type !== original.type) {
    payload.type = values.type
  }
  if (values.start_date !== original.start_date) {
    payload.start_date = values.start_date
  }
  if (values.callback_date !== original.callback_date) {
    payload.callback_date = values.callback_date
  }
  if (!sameIdSet(values.supervisor_ids, original.supervisors.map((supervisor) => supervisor.id))) {
    payload.supervisor_ids = values.supervisor_ids
  }
  // Positional comparison, not a set one: moving a partecipante between slots
  // IS a change, and `sameIdSet` would call it a no-op.
  if (!sameManagerSlots(values.participant_slots, managerSlotsFromRefs(original.participants))) {
    payload.participant_slots = values.participant_slots
  }
  if (values.description !== original.description) {
    payload.description = values.description
  }
  if (values.internal_notes !== original.internal_notes) {
    payload.internal_notes = values.internal_notes
  }
  if (values.is_force_closed !== original.is_force_closed) {
    payload.is_force_closed = values.is_force_closed
  }

  const forceCloseReason = values.is_force_closed ? values.force_close_reason : null
  if (forceCloseReason !== original.force_close_reason) {
    payload.force_close_reason = forceCloseReason
  }

  const originalLineIds = original.quote_lines.map((line) => line.id)
  if (!sameIdSet(values.quote_line_ids, originalLineIds)) {
    payload.quote_line_ids = values.quote_line_ids
  }

  return payload
}
