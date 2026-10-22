import type { TFunction } from 'i18next'
import { useTranslation } from 'react-i18next'
import { CalendarDays, Paperclip, UserCheck } from 'lucide-react'
import { RecordField, RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { formatAbsenceDuration, formatAbsencePeriod } from '@/features/absences/requests/absence-request-format'
import { AbsenceRequestAttachment } from '@/features/absences/requests/absence-request-attachment'
import type { AbsenceRequestActions } from '@/features/absences/requests/use-absence-request-actions'
import type { AbsenceRequest } from '@/features/absences/types'
import { formatDateTime } from '@/lib/formatting/date-display'

function modeLabel(request: AbsenceRequest, t: TFunction): string {
  if (request.is_full_day) return t('absences.requests.detail.fullDay')
  if (request.day_part) {
    return `${t('absences.requests.detail.halfDay')} - ${t(`absences.requests.form.dayParts.${request.day_part}`)}`
  }
  return t('absences.requests.detail.hourly')
}

interface AbsenceRequestDetailBodyProps {
  request: AbsenceRequest
  actions: AbsenceRequestActions
}

/**
 * Read-only data of a request in record sections (spec 0287 D-2): the request itself, its approval
 * and the attachment. Person, type and status live in the identity band above.
 */
export function AbsenceRequestDetailBody({ request, actions }: AbsenceRequestDetailBodyProps) {
  const { t } = useTranslation()
  const prefix = 'absences.requests.detail'

  return (
    <RecordSectionsGrid>
      <RecordSection title={t(`${prefix}.sections.request`)} icon={<CalendarDays />}>
        <RecordFieldList>
          <RecordField label={t(`${prefix}.period`)}>
            {formatAbsencePeriod(request)} ({modeLabel(request, t)})
          </RecordField>
          <RecordField label={t(`${prefix}.duration`)}>{formatAbsenceDuration(request, t)}</RecordField>
          {request.submitted_at ? (
            <RecordField label={t(`${prefix}.submittedAt`)}>{formatDateTime(request.submitted_at)}</RecordField>
          ) : null}
          {request.note ? <RecordField label={t(`${prefix}.note`)}>{request.note}</RecordField> : null}
        </RecordFieldList>
      </RecordSection>
      <RecordSection title={t(`${prefix}.sections.approval`)} icon={<UserCheck />}>
        <RecordFieldList>
          <RecordField label={t(`${prefix}.approvers`)}>
            {request.approvers.length > 0
              ? request.approvers.map((approver) => approver.name).join(', ')
              : t(`${prefix}.noApprovers`)}
          </RecordField>
          {request.decided_by ? (
            <RecordField label={t(`${prefix}.decidedBy`)}>{request.decided_by.name}</RecordField>
          ) : null}
          {request.decided_at ? (
            <RecordField label={t(`${prefix}.decidedAt`)}>{formatDateTime(request.decided_at)}</RecordField>
          ) : null}
          {request.rejection_reason ? (
            <RecordField label={t(`${prefix}.rejectionReason`)}>{request.rejection_reason}</RecordField>
          ) : null}
        </RecordFieldList>
      </RecordSection>
      <RecordSection title={t(`${prefix}.sections.attachment`)} icon={<Paperclip />} full>
        <AbsenceRequestAttachment request={request} actions={actions} />
      </RecordSection>
    </RecordSectionsGrid>
  )
}
