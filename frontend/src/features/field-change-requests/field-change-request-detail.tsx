import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { ListChecks } from 'lucide-react'
import {
  DetailEmpty,
  DetailField,
  DetailGrid,
  DetailHero,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import { safeInternalPath } from '@/features/notifications/safe-internal-path'
import { FieldChangeRequestActions } from '@/features/field-change-requests/field-change-request-actions'
import { FieldChangeRequestStatusBadge } from '@/features/field-change-requests/field-change-request-status-badge'
import type { FieldChangeRequestResource } from '@/features/field-change-requests/types'

interface FieldChangeRequestDetailViewProps {
  request: FieldChangeRequestResource
  /** Called with the freshly resolved resource once approve/reject succeeds. */
  onChanged: (request: FieldChangeRequestResource) => void
}

/**
 * Read-only detail of a single field change request (spec 0078, AC-047):
 * module, record (deep-linked via `subject_path` when resolvable), field,
 * current → requested value, motivation, requester + request date, status,
 * handler + handling date and note. Approve/Reject live in
 * `FieldChangeRequestActions`, each gated on its own `can.*` flag off the
 * resource — never a client-side permission guess. `resource_label`/
 * `field_label` are i18n KEYS on the wire (D-1's config, never resolved
 * server-side), translated here exactly like the proposal dialog does.
 */
export function FieldChangeRequestDetailView({ request, onChanged }: FieldChangeRequestDetailViewProps) {
  const { t } = useTranslation()
  const subjectPath = safeInternalPath(request.subject_path)
  const requestedAt = formatDateTime(request.requested_at)
  const handledAt = formatDateTime(request.handled_at)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={request.field_label} icon={<ListChecks />} />}
        title={t(request.field_label)}
        subtitle={t(request.resource_label)}
        badges={<FieldChangeRequestStatusBadge status={request.status} />}
      />

      <FieldChangeRequestActions request={request} onHandled={onChanged} />

      <DetailSection>
        <DetailGrid>
          <DetailField label={t('fieldChangeRequests.detail.record')}>
            {subjectPath ? (
              <Link to={subjectPath} className="text-primary underline-offset-2 hover:underline">
                {request.subject_label}
              </Link>
            ) : (
              request.subject_label
            )}
          </DetailField>
          <DetailField label={t('fieldChangeRequests.detail.field')}>
            {t(request.field_label)}
          </DetailField>
          <DetailField label={t('fieldChangeRequests.detail.currentValue')}>
            {request.current_label ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('fieldChangeRequests.detail.requestedValue')}>
            {request.requested_label ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('fieldChangeRequests.detail.reason')} full>
            {request.reason ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('fieldChangeRequests.detail.requestedBy')}>
            {request.requested_by.name}
          </DetailField>
          <DetailField label={t('fieldChangeRequests.detail.requestedAt')}>
            {requestedAt ? requestedAt : <DetailEmpty />}
          </DetailField>
          <DetailField label={t('fieldChangeRequests.detail.handledBy')}>
            {request.handled_by ? request.handled_by.name : <DetailEmpty />}
          </DetailField>
          <DetailField label={t('fieldChangeRequests.detail.handledAt')}>
            {handledAt ? handledAt : <DetailEmpty />}
          </DetailField>
          <DetailField label={t('fieldChangeRequests.detail.handlingNote')} full>
            {request.handling_note ?? <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>
    </DetailPanel>
  )
}
