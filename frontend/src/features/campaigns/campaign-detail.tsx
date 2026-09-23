import { useTranslation } from 'react-i18next'
import { RecordCanvas, RecordCard, RecordMeta } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCollaborationCard } from '@/components/detail/record-collaboration-card'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import {
  CampaignDetailHeader,
  CampaignDetailStats,
} from '@/features/campaigns/campaign-detail-header'
import { CampaignDetailSections } from '@/features/campaigns/campaign-detail-sections'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { CampaignDetailWithPermissions as CampaignDetailData } from '@/features/campaigns/types'

interface CampaignDetailViewProps {
  campaign: CampaignDetailData
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single campaign, rendered as an enterprise-CRM record
 * on the same kit Opportunita', Utenti and Lead use: the identity/KPI/sections
 * card on the left, the activity card on the right, a metadata footer.
 * Container-query driven (`RecordCanvas`) so the same tree renders correctly
 * both inside a resizable Sheet and on the full-bleed `/campaigns/:id` page.
 *
 * The 3 classification fields always show the EFFECTIVE value (BR-2: read
 * through the project when linked); the 4 geo fields + `geo_scope` show the
 * EFFECTIVE (merged) tuple instead (BR-5, spec 0027) — `CampaignResource`
 * already resolves both server-side.
 */
export function CampaignDetailView({ campaign, onEdit }: CampaignDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(campaign.created_at)
  const collaborationTabs = campaign.permissions.actions.view_activity
    ? [activityLogTab('campaigns', campaign.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        side={collaborationTabs.length > 0 ? <RecordCollaborationCard tabs={collaborationTabs} /> : null}
      >
        <RecordCard>
          <CampaignDetailHeader campaign={campaign} onEdit={onEdit} />
          <CampaignDetailStats campaign={campaign} />
          <CampaignDetailSections campaign={campaign} />
        </RecordCard>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('campaigns.columns.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
