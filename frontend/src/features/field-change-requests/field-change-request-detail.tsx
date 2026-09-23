import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { ListChecks } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import {
  RecordCanvas,
  RecordCard,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { RecordLink } from '@/components/detail/record-link'
import { findModuleRecordByPath } from '@/features/modules/module-registry'
import { safeInternalPath } from '@/features/notifications/safe-internal-path'
import { FieldChangeRequestActions } from '@/features/field-change-requests/field-change-request-actions'
import { FieldChangeRequestStatusBadge } from '@/features/field-change-requests/field-change-request-status-badge'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { FieldChangeRequestResource } from '@/features/field-change-requests/types'

/** Header actions render inline, without `FieldChangeRequestActions`' own full-width bar chrome. */
const HEADER_ACTIONS_CLASS = 'flex shrink-0 items-center gap-1.5'

interface FieldChangeRequestDetailViewProps {
  request: FieldChangeRequestResource
  /** Called with the freshly resolved resource once approve/reject succeeds. */
  onChanged: (request: FieldChangeRequestResource) => void
}

/**
 * Read-only detail of a single field change request (spec 0078, AC-047),
 * rendered as an enterprise-CRM record (Opportunita' reference kit): module,
 * record (deep-linked via `subject_path` when resolvable), field, current →
 * requested value, motivation, requester + request date, status, handler +
 * handling date and note. A request has no edit surface of its own — its
 * Approve/Reject actions (`FieldChangeRequestActions`, each gated on its own
 * `can.*` flag off the resource) sit in the identity header's `actions`
 * slot, in place of the Edit button every other record shows there.
 * `resource_label`/`field_label` are i18n KEYS on the wire (D-1's config,
 * never resolved server-side), translated here exactly like the proposal
 * dialog does.
 */
export function FieldChangeRequestDetailView({ request, onChanged }: FieldChangeRequestDetailViewProps) {
  const { t } = useTranslation()
  const subjectPath = safeInternalPath(request.subject_path)
  const subjectRecord = subjectPath ? findModuleRecordByPath(subjectPath) : null
  const requestedAt = formatDateTime(request.requested_at)
  const handledAt = formatDateTime(request.handled_at)

  return (
    <RecordCanvas>
      <RecordBody>
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={request.field_label} icon={<ListChecks />} />}
            title={t(request.field_label)}
            subtitle={t(request.resource_label)}
            badges={<FieldChangeRequestStatusBadge status={request.status} />}
            actions={
              <FieldChangeRequestActions
                request={request}
                onHandled={onChanged}
                className={HEADER_ACTIONS_CLASS}
              />
            }
          />
          <RecordSectionsGrid>
            <RecordSection title={t('fieldChangeRequests.detail.details')} full>
              <RecordFieldList>
                <RecordField label={t('fieldChangeRequests.detail.record')}>
                  {subjectRecord ? (
                    <RecordLink domain={subjectRecord.domain} id={subjectRecord.id} className="text-primary">
                      {request.subject_label}
                    </RecordLink>
                  ) : subjectPath ? (
                    <Link to={subjectPath} className="text-primary underline-offset-2 hover:underline">
                      {request.subject_label}
                    </Link>
                  ) : (
                    request.subject_label
                  )}
                </RecordField>
                <RecordField label={t('fieldChangeRequests.detail.field')}>
                  {t(request.field_label)}
                </RecordField>
                <RecordField label={t('fieldChangeRequests.detail.currentValue')}>
                  {request.current_label ?? <DetailEmpty />}
                </RecordField>
                <RecordField label={t('fieldChangeRequests.detail.requestedValue')}>
                  {request.requested_label ?? <DetailEmpty />}
                </RecordField>
                <RecordField label={t('fieldChangeRequests.detail.reason')}>
                  {request.reason ?? <DetailEmpty />}
                </RecordField>
                <RecordField label={t('fieldChangeRequests.detail.requestedBy')}>
                  {request.requested_by.name}
                </RecordField>
                <RecordField label={t('fieldChangeRequests.detail.requestedAt')}>
                  {requestedAt ? requestedAt : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('fieldChangeRequests.detail.handledBy')}>
                  {request.handled_by ? request.handled_by.name : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('fieldChangeRequests.detail.handledAt')}>
                  {handledAt ? handledAt : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('fieldChangeRequests.detail.handlingNote')}>
                  {request.handling_note ?? <DetailEmpty />}
                </RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>
    </RecordCanvas>
  )
}
