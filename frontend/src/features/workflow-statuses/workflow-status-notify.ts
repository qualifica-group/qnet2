import { z } from 'zod'
import type { TFunction } from 'i18next'
import type { WorkflowStatusNotify } from '@/features/workflow-statuses/types'

/** Backend `WorkflowStatusNotify` limits (spec 0419). */
export const STATUS_NOTIFY_SUBJECT_MAX = 191
export const STATUS_NOTIFY_MESSAGE_MAX = 5000
export const STATUS_NOTIFY_MAX_IDS = 50
export const STATUS_NOTIFY_MAX_EMAILS = 20

/** A fresh configuration: the offer's team is the common recipient once the email is switched on. */
export function blankStatusNotify(): WorkflowStatusNotify {
  return { subject: '', message: '', record_team: true, user_ids: [], role_ids: [], emails: [] }
}

/** How many recipients a configuration reaches, the offer's team counting as one. */
export function statusNotifyRecipientCount(notify: WorkflowStatusNotify): number {
  return (notify.record_team ? 1 : 0) + notify.user_ids.length + notify.role_ids.length + notify.emails.length
}

/** Mirrors the backend contract: subject and message required, at least one recipient. */
export function buildStatusNotifySchema(t: TFunction) {
  return z
    .object({
      subject: z
        .string()
        .trim()
        .min(1, t('workflowStatuses.notify.subjectRequired'))
        .max(STATUS_NOTIFY_SUBJECT_MAX, t('workflowStatuses.notify.subjectMax')),
      message: z
        .string()
        .trim()
        .min(1, t('workflowStatuses.notify.messageRequired'))
        .max(STATUS_NOTIFY_MESSAGE_MAX, t('workflowStatuses.notify.messageMax')),
      record_team: z.boolean(),
      user_ids: z.array(z.number().int().positive()).max(STATUS_NOTIFY_MAX_IDS),
      role_ids: z.array(z.number().int().positive()).max(STATUS_NOTIFY_MAX_IDS),
      emails: z.array(z.string().email()).max(STATUS_NOTIFY_MAX_EMAILS),
    })
    .refine((notify) => statusNotifyRecipientCount(notify) > 0, {
      message: t('workflowStatuses.notify.recipientRequired'),
      path: ['record_team'],
    })
}
