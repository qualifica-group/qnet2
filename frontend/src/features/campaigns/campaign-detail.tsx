import { useTranslation } from 'react-i18next'
import { History } from 'lucide-react'
import {
  RecordCanvas,
  RecordCard,
  RecordMeta,
  RecordSection,
} from '@/components/detail/record-panel'
import {
  RECORD_BODY_GRID_CLASS,
  RECORD_BODY_WITH_SIDE_CLASS,
  RECORD_COLUMN_CLASS,
} from '@/components/detail/record-layout'
import { cn } from '@/lib/utils'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
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
  const canViewActivity = campaign.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <div className={cn(RECORD_BODY_GRID_CLASS, canViewActivity && RECORD_BODY_WITH_SIDE_CLASS)}>
        <div className={RECORD_COLUMN_CLASS}>
          <RecordCard>
            <CampaignDetailHeader campaign={campaign} onEdit={onEdit} />
            <CampaignDetailStats campaign={campaign} />
            <CampaignDetailSections campaign={campaign} />
          </RecordCard>
        </div>

        {canViewActivity ? (
          <div className={RECORD_COLUMN_CLASS}>
            <RecordCard className="p-4">
              <RecordSection title={t('activityLog.title')} icon={<History />}>
                <ActivityLogSection resource="campaigns" id={campaign.id} />
              </RecordSection>
            </RecordCard>
          </div>
        ) : null}
      </div>

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
