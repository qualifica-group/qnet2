import { useTranslation } from 'react-i18next'
import { CalendarRange, Megaphone, Pencil, Target, Wallet } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader, RecordStat, RecordStatStrip } from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { GeoScopeBadge } from '@/features/geo/geo-scope-badge'
import { geoScopePlaceName } from '@/features/geo/geo-scope'
import { formatDecimal } from '@/features/products/column-renderers'
import { statusBadgeClassName } from '@/features/projects/status-badge-classes'
import { formatDate } from '@/lib/formatting/date-display'
import type { CampaignDetailWithPermissions as CampaignDetailData } from '@/features/campaigns/types'

/**
 * Identity band and KPI strip of the campaign record card. Kept in one file:
 * both pieces read the same handful of top-level fields and are always mounted
 * together — the same split `opportunity-detail-header.tsx` makes.
 */

interface CampaignDetailHeaderProps {
  campaign: CampaignDetailData
  /** Opens the module's existing edit surface; absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Identity band: monogram, name, code subtitle, and the three badges that say
 * WHAT KIND of campaign this is — its pipeline status, whether it stands alone
 * or inherits from a project (BR-2), and the geographic scope it covers.
 *
 * The status is NOT repeated in the sections below: it lives here, the way
 * Opportunita' keeps its own status badge in the band alone.
 */
export function CampaignDetailHeader({ campaign, onEdit }: CampaignDetailHeaderProps) {
  const { t } = useTranslation()
  const canEdit = Boolean(onEdit) && campaign.permissions.resource.update
  const geoPlace = campaign.geo_scope ? geoScopePlaceName(campaign.geo_scope, campaign) : null

  return (
    <RecordCardHeader
      media={
        <DetailMonogram
          name={campaign.name}
          icon={<Megaphone />}
          className="size-10 text-base [&>svg]:size-5"
        />
      }
      title={campaign.name}
      subtitle={campaign.code}
      badges={
        <>
          {campaign.pipeline_status ? (
            <Badge
              variant="secondary"
              className={cn(statusBadgeClassName(campaign.pipeline_status.color))}
            >
              {campaign.pipeline_status.name}
            </Badge>
          ) : null}
          {campaign.derived_from_project ? (
            <Badge variant="secondary">{t('campaigns.detail.linkedToProject')}</Badge>
          ) : (
            <Badge variant="outline">{t('campaigns.detail.standalone')}</Badge>
          )}
          {campaign.geo_scope && geoPlace ? (
            <GeoScopeBadge scope={campaign.geo_scope} place={geoPlace} />
          ) : null}
        </>
      }
      actions={
        canEdit ? (
          <Button size="sm" onClick={onEdit}>
            <Pencil aria-hidden="true" />
            {t('common.edit')}
          </Button>
        ) : null
      }
    />
  )
}

interface CampaignDetailStatsProps {
  campaign: CampaignDetailData
}

/** KPI strip: what the campaign costs, what it is meant to produce, and the window it runs in. */
export function CampaignDetailStats({ campaign }: CampaignDetailStatsProps) {
  const { t } = useTranslation()

  return (
    <RecordStatStrip>
      <RecordStat
        label={t('campaigns.form.totalBudget')}
        icon={<Wallet />}
        value={campaign.total_budget !== null ? formatDecimal(campaign.total_budget) : <DetailEmpty />}
      />
      <RecordStat
        label={t('campaigns.form.targetLead')}
        icon={<Target />}
        value={campaign.target_lead ?? <DetailEmpty />}
      />
      <RecordStat
        label={t('campaigns.form.startDate')}
        icon={<CalendarRange />}
        value={formatDate(campaign.start_date) || <DetailEmpty />}
      />
      <RecordStat
        label={t('campaigns.form.endDate')}
        value={formatDate(campaign.end_date) || <DetailEmpty />}
      />
    </RecordStatStrip>
  )
}
