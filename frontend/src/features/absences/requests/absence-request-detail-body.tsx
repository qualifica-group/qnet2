import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { formatAbsenceDuration, formatAbsencePeriod } from '@/features/absences/requests/absence-request-format'
import { AbsenceRequestAttachment } from '@/features/absences/requests/absence-request-attachment'
import { AbsenceRequestStatusBadge } from '@/features/absences/requests/absence-request-status-badge'
import type { AbsenceRequestActions } from '@/features/absences/requests/use-absence-request-actions'
import type { AbsenceRequest } from '@/features/absences/types'
import { formatDateTime } from '@/lib/formatting/date-display'

interface FieldProps {
  label: string
  children: ReactNode
}

function Field({ label, children }: FieldProps) {
  return (
    <div className="min-w-0">
      <dt className="text-xs text-muted-foreground">{label}</dt>
      <dd className="break-words text-sm">{children}</dd>
    </div>
  )
}

interface AbsenceRequestDetailBodyProps {
  request: AbsenceRequest
  actions: AbsenceRequestActions
}

/** Read-only data of a request: who, what, when, state, approvers, decision and attachment. */
export function AbsenceRequestDetailBody({ request, actions }: AbsenceRequestDetailBodyProps) {
  const { t } = useTranslation()
  const prefix = 'absences.requests.detail'

  return (
    <dl className="grid gap-3 sm:grid-cols-2">
      <Field label={t(`${prefix}.person`)}>{request.user.name}</Field>
      <Field label={t(`${prefix}.type`)}>{request.absence_type.name}</Field>
      <Field label={t(`${prefix}.status`)}>
        <AbsenceRequestStatusBadge status={request.status} />
      </Field>
      <Field label={t(`${prefix}.period`)}>
        {formatAbsencePeriod(request)} ({t(request.is_full_day ? `${prefix}.fullDay` : `${prefix}.hourly`)})
      </Field>
      <Field label={t(`${prefix}.duration`)}>{formatAbsenceDuration(request, t)}</Field>
      {request.submitted_at ? <Field label={t(`${prefix}.submittedAt`)}>{formatDateTime(request.submitted_at)}</Field> : null}
      <Field label={t(`${prefix}.approvers`)}>
        {request.approvers.length > 0
          ? request.approvers.map((approver) => approver.name).join(', ')
          : t(`${prefix}.noApprovers`)}
      </Field>
      {request.decided_by ? <Field label={t(`${prefix}.decidedBy`)}>{request.decided_by.name}</Field> : null}
      {request.decided_at ? <Field label={t(`${prefix}.decidedAt`)}>{formatDateTime(request.decided_at)}</Field> : null}
      {request.rejection_reason ? (
        <div className="sm:col-span-2">
          <Field label={t(`${prefix}.rejectionReason`)}>{request.rejection_reason}</Field>
        </div>
      ) : null}
      {request.note ? (
        <div className="sm:col-span-2">
          <Field label={t(`${prefix}.note`)}>{request.note}</Field>
        </div>
      ) : null}
      <div className="sm:col-span-2">
        <Field label={t(`${prefix}.attachment`)}>
          <AbsenceRequestAttachment request={request} actions={actions} />
        </Field>
      </div>
    </dl>
  )
}
