import { useTranslation } from 'react-i18next'
import { Percent } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCard,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCollaborationCard } from '@/components/detail/record-collaboration-card'
import { RecordEditButton } from '@/components/detail/record-edit-button'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { formatDateTime } from '@/features/table/cell-renderers'
import { formatEuro } from '@/features/invoices/invoice-format'
import {
  ENASARCO_PARAMETERS_DOMAIN,
  type EnasarcoParameterDetailWithPermissions,
} from '@/features/enasarco-parameters/types'

interface EnasarcoParameterDetailViewProps {
  parameter: EnasarcoParameterDetailWithPermissions
  /** Opens the module's edit surface; absent = no edit affordance. */
  onEdit?: () => void
}

/** Read-only record view of the ENASARCO parameters of one year. */
export function EnasarcoParameterDetailView({ parameter, onEdit }: EnasarcoParameterDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(parameter.created_at)
  const updatedAt = formatDateTime(parameter.updated_at)
  const canEdit = parameter.permissions.resource.update
  const canViewActivity = parameter.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <RecordBody
        side={
          canViewActivity ? (
            <RecordCollaborationCard
              tabs={[activityLogTab(ENASARCO_PARAMETERS_DOMAIN, parameter.id, t('activityLog.title'))]}
            />
          ) : null
        }
      >
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={String(parameter.year)} icon={<Percent />} />}
            title={t('enasarcoParameters.detail.title', { year: parameter.year })}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('enasarcoParameters.detail.parameters')} full>
              <RecordFieldList>
                <RecordField label={t('enasarcoParameters.detail.total_rate')}>{`${parameter.total_rate}%`}</RecordField>
                <RecordField label={t('enasarcoParameters.detail.agent_share_percent')}>
                  {`${parameter.agent_share_percent}%`}
                </RecordField>
                <RecordField label={t('enasarcoParameters.detail.single_firm_ceiling')}>
                  {formatEuro(parameter.single_firm_ceiling)}
                </RecordField>
                <RecordField label={t('enasarcoParameters.detail.multi_firm_ceiling')}>
                  {formatEuro(parameter.multi_firm_ceiling)}
                </RecordField>
                <RecordField label={t('enasarcoParameters.detail.notes')}>
                  {parameter.notes ? parameter.notes : <DetailEmpty />}
                </RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      {createdAt || updatedAt ? (
        <RecordMeta>
          {createdAt ? (
            <span>
              <span className="font-medium">{t('enasarcoParameters.detail.created_at')}</span>{' '}
              <span aria-hidden="true">·</span> {createdAt}
            </span>
          ) : null}
          {updatedAt ? (
            <span>
              <span className="font-medium">{t('enasarcoParameters.detail.updated_at')}</span>{' '}
              <span aria-hidden="true">·</span> {updatedAt}
            </span>
          ) : null}
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
