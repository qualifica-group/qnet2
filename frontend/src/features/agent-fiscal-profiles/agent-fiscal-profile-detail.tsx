import { useTranslation } from 'react-i18next'
import { IdCard } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { RecordEditButton } from '@/components/detail/record-edit-button'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { formatDateTime } from '@/features/table/cell-renderers'
import { formatDate } from '@/lib/formatting/date-display'
import {
  AGENT_FISCAL_PROFILES_DOMAIN,
  type AgentFiscalProfileDetailWithPermissions,
} from '@/features/agent-fiscal-profiles/types'

interface AgentFiscalProfileDetailViewProps {
  profile: AgentFiscalProfileDetailWithPermissions
  /** Opens the module's edit surface; absent = no edit affordance. */
  onEdit?: () => void
}

function percent(value: string | null): string | null {
  return value === null ? null : `${value}%`
}

/** Read-only record view of an agent fiscal profile: validity, withholding, ENASARCO, notes. */
export function AgentFiscalProfileDetailView({ profile, onEdit }: AgentFiscalProfileDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(profile.created_at)
  const updatedAt = formatDateTime(profile.updated_at)
  const canEdit = profile.permissions.resource.update
  const canViewActivity = profile.permissions.actions.view_activity
  const yesNo = (value: boolean) => t(value ? 'common.yes' : 'common.no')
  const until = profile.valid_until ? formatDate(profile.valid_until) : t('agentFiscalProfiles.detail.open')

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={canViewActivity ? [activityLogTab(AGENT_FISCAL_PROFILES_DOMAIN, profile.id, t('activityLog.title'))] : []}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={profile.referent.name} icon={<IdCard />} />}
            title={profile.referent.name}
            subtitle={`${formatDate(profile.valid_from)} - ${until}`}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
        }
      >
        <RecordSectionsGrid>
          <RecordSection title={t('agentFiscalProfiles.detail.fiscalData')} full>
            <RecordFieldList>
              <RecordField label={t('agentFiscalProfiles.detail.withholdingEnabled')}>
                {yesNo(profile.withholding_enabled)}
              </RecordField>
              <RecordField label={t('agentFiscalProfiles.detail.withholdingRate')}>
                {percent(profile.withholding_rate) ?? <DetailEmpty />}
              </RecordField>
              <RecordField label={t('agentFiscalProfiles.detail.withholdingBasePercent')}>
                {percent(profile.withholding_base_percent) ?? <DetailEmpty />}
              </RecordField>
              <RecordField label={t('agentFiscalProfiles.detail.enasarcoEnabled')}>
                {yesNo(profile.enasarco_enabled)}
              </RecordField>
              <RecordField label={t('agentFiscalProfiles.detail.mandateType')}>
                {profile.enasarco_mandate_type ? (
                  t(`enums.enasarco_mandate_type.${profile.enasarco_mandate_type}`)
                ) : (
                  <DetailEmpty />
                )}
              </RecordField>
              <RecordField label={t('agentFiscalProfiles.detail.notes')}>
                {profile.notes ? profile.notes : <DetailEmpty />}
              </RecordField>
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordBody>

      {createdAt || updatedAt ? (
        <RecordMeta>
          {createdAt ? (
            <span>
              <span className="font-medium">{t('agentFiscalProfiles.detail.created_at')}</span>{' '}
              <span aria-hidden="true">·</span> {createdAt}
            </span>
          ) : null}
          {updatedAt ? (
            <span>
              <span className="font-medium">{t('agentFiscalProfiles.detail.updated_at')}</span>{' '}
              <span aria-hidden="true">·</span> {updatedAt}
            </span>
          ) : null}
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
