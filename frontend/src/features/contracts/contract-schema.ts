import { z } from 'zod'
import type { TFunction } from 'i18next'

/** Backend `termination_reason` column limit (`max:2000`). */
const TERMINATION_REASON_MAX_LENGTH = 2000

/** Today as `YYYY-MM-DD`, matching the wire format of an `<input type="date">`. */
function todayIsoDate(): string {
  return new Date().toISOString().slice(0, 10)
}

/** `true` when `value` (a `YYYY-MM-DD` string) is today or earlier. Empty values are left to `.min(1)`. */
function isNotFuture(value: string): boolean {
  return value === '' || value <= todayIsoDate()
}

/**
 * "Valida contratto" (BR-3): `validated_at` defaults to today and can never be
 * future; `contract_status_id` is an OPTIONAL destination status — `null`
 * means "do not change the status", never sent to the server (see
 * `buildValidatePayload`).
 */
export function buildValidateContractSchema(t: TFunction) {
  return z.object({
    validated_at: z
      .string()
      .min(1, t('contracts.actions.validateDialog.dateRequired'))
      .refine(isNotFuture, t('contracts.actions.validateDialog.dateFuture')),
    contract_status_id: z.number().nullable(),
  })
}

export type ValidateContractFormValues = z.infer<ReturnType<typeof buildValidateContractSchema>>

export function validateContractDefaultValues(): ValidateContractFormValues {
  return { validated_at: todayIsoDate(), contract_status_id: null }
}

/**
 * "Programma contratto": `expiry_date` and the destination status are
 * mandatory (D-2 — "Programmato" is a deletable custom row, so the client
 * must pick it, there is no system_key fallback); `renewal_date` is optional
 * and must not fall after `expiry_date`.
 */
export function buildScheduleContractSchema(t: TFunction) {
  return z
    .object({
      expiry_date: z.string().min(1, t('contracts.actions.scheduleDialog.expiryRequired')),
      renewal_date: z.string().nullable(),
      contract_status_id: z.number({ message: t('contracts.actions.scheduleDialog.statusRequired') }).nullable(),
    })
    .refine((value) => value.contract_status_id !== null, {
      message: t('contracts.actions.scheduleDialog.statusRequired'),
      path: ['contract_status_id'],
    })
    .refine(
      (value) => !value.renewal_date || !value.expiry_date || value.renewal_date <= value.expiry_date,
      {
        message: t('contracts.actions.scheduleDialog.renewalAfterExpiry'),
        path: ['renewal_date'],
      },
    )
}

export type ScheduleContractFormValues = z.infer<ReturnType<typeof buildScheduleContractSchema>>

export function scheduleContractDefaultValues(): ScheduleContractFormValues {
  return { expiry_date: '', renewal_date: null, contract_status_id: null }
}

/**
 * "Disdici contratto" (BR-4): date and motivation are mandatory;
 * `contract_status_id` is an optional destination restricted to the
 * `closed_lost` group — the client offers the same active-status picker as
 * "Programma" (the frozen for-select contract has no group filter param) and
 * the server is the final authority on the group match (422 otherwise).
 */
export function buildTerminateContractSchema(t: TFunction) {
  return z.object({
    terminated_at: z
      .string()
      .min(1, t('contracts.actions.terminateDialog.dateRequired'))
      .refine(isNotFuture, t('contracts.actions.terminateDialog.dateFuture')),
    termination_reason: z
      .string()
      .min(1, t('contracts.actions.terminateDialog.reasonRequired'))
      .max(TERMINATION_REASON_MAX_LENGTH, t('contracts.actions.terminateDialog.reasonMax')),
    contract_status_id: z.number().nullable(),
  })
}

export type TerminateContractFormValues = z.infer<ReturnType<typeof buildTerminateContractSchema>>

export function terminateContractDefaultValues(): TerminateContractFormValues {
  return { terminated_at: todayIsoDate(), termination_reason: '', contract_status_id: null }
}

/**
 * "Riattiva contratto" sul percorso DISDETTO (direttiva utente 2026-08-31):
 * lo stato di destinazione e' obbligatorio, perche' nessuna colonna ha mai
 * memorizzato quello precedente alla disdetta. Il percorso SOSPESO non usa
 * questo schema: non ha form, ripristina da solo lo stato pre-sospensione.
 */
export function buildReactivateContractSchema(t: TFunction) {
  return z
    .object({
      contract_status_id: z.number().nullable(),
    })
    .refine((value) => value.contract_status_id !== null, {
      message: t('contracts.actions.reactivateDialog.statusRequired'),
      path: ['contract_status_id'],
    })
}

export type ReactivateContractFormValues = z.infer<ReturnType<typeof buildReactivateContractSchema>>

export function reactivateContractDefaultValues(): ReactivateContractFormValues {
  return { contract_status_id: null }
}

/**
 * "Modifica dati" — the PATCH-editable subset (data_contract): dates,
 * payment notes and comments. Per-field visibility/editability is enforced
 * by `MetaField` at render time (AC-039), not here. The contract status is
 * NOT part of this form (user directive 2026-08-31): it is driven by the
 * domain actions alone, so nothing here can leave the NOT NULL FK empty.
 */
export function buildEditContractSchema() {
  return z.object({
    renewal_date: z.string().nullable(),
    expiry_date: z.string().nullable(),
    payment_notes: z.string().nullable(),
    comments: z.string().nullable(),
  })
}

export type EditContractFormValues = z.infer<ReturnType<typeof buildEditContractSchema>>
