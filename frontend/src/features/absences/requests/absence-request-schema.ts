import { z } from 'zod'
import type { TFunction } from 'i18next'
import { ABSENCE_DAY_PARTS } from '@/features/absences/types'
import { timeValueToMinutes } from '@/features/time-entries/time-entry-format'

const NOTE_MAX_LENGTH = 2000
export const ABSENCE_MODES = ['full_day', 'half_day', 'hours'] as const
export type AbsenceMode = (typeof ABSENCE_MODES)[number]
export const REJECTION_REASON_MAX_LENGTH = 1000

/**
 * Zod schema of the request form. `mode` says which of the date/part/duration fields count (the payload builder
 * turns it into `is_full_day` + `day_part` + `requested_minutes`); `requested_minutes` is an `HH:MM` text.
 */
export function buildAbsenceRequestSchema(t: TFunction) {
  return z
    .object({
      absence_type_id: z.number().nullable(),
      /** Person asked for: only chosen on creation by who holds `absence-requests.manageAll`. */
      user_id: z.number().nullable(),
      mode: z.enum(ABSENCE_MODES),
      day_part: z.enum(ABSENCE_DAY_PARTS),
      starts_on: z.string(),
      ends_on: z.string(),
      requested_minutes: z.string(),
      note: z.string().max(NOTE_MAX_LENGTH, t('absences.requests.form.noteTooLong')),
    })
    .superRefine((values, ctx) => {
      if (values.absence_type_id === null) {
        ctx.addIssue({ code: 'custom', path: ['absence_type_id'], message: t('absences.requests.form.typeRequired') })
      }
      if (values.starts_on === '') {
        ctx.addIssue({ code: 'custom', path: ['starts_on'], message: t('absences.requests.form.dateRequired') })
      }
      if (values.mode === 'full_day') {
        if (values.ends_on === '') {
          ctx.addIssue({ code: 'custom', path: ['ends_on'], message: t('absences.requests.form.dateRequired') })
        } else if (values.starts_on !== '' && values.ends_on < values.starts_on) {
          ctx.addIssue({ code: 'custom', path: ['ends_on'], message: t('absences.requests.form.endsBeforeStart') })
        }
        return
      }
      if (values.mode === 'half_day') {
        return
      }
      if (values.requested_minutes.trim() === '') {
        ctx.addIssue({
          code: 'custom',
          path: ['requested_minutes'],
          message: t('absences.requests.form.durationRequired'),
        })
      } else if (!((timeValueToMinutes(values.requested_minutes) ?? 0) > 0)) {
        ctx.addIssue({
          code: 'custom',
          path: ['requested_minutes'],
          message: t('absences.requests.form.durationInvalid'),
        })
      }
    })
}

export type AbsenceRequestFormValues = z.infer<ReturnType<typeof buildAbsenceRequestSchema>>

/** Zod schema of the reject dialog: the reason is mandatory. */
export function buildRejectSchema(t: TFunction) {
  return z.object({
    rejection_reason: z
      .string()
      .trim()
      .min(1, t('absences.requests.reject.reasonRequired'))
      .max(REJECTION_REASON_MAX_LENGTH, t('absences.requests.reject.reasonTooLong')),
  })
}

export type RejectFormValues = z.infer<ReturnType<typeof buildRejectSchema>>
