import { useTranslation } from 'react-i18next'
import { CalendarRange, FolderKanban, Globe, History, Megaphone, Tags, Wallet } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import {
  DetailEmpty,
  DetailField,
  DetailGrid,
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import { formatDate } from '@/lib/formatting/date-display'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { formatDecimal } from '@/features/products/column-renderers'
import { GeoScopeBadge } from '@/features/geo/geo-scope-badge'
import { geoScopePlaceName } from '@/features/geo/geo-scope'
import { ProductLinesReadOnlyList } from '@/features/product-lines/product-lines-read-only-list'
import type { CampaignDetailWithPermissions as CampaignDetailData } from '@/features/campaigns/types'

interface CampaignDetailViewProps {
  campaign: CampaignDetailData
}

/**
 * Read-only detail of a single campaign, fetched fresh from the
 * (re-authorized) detail endpoint. Composed from the shared detail kit;
 * rendered by the dedicated detail page (spec 0023, mirrors 0022/Projects).
 * The 3 classification fields always show the EFFECTIVE value (BR-2: read
 * through the project when linked); the 4 geo fields + `geo_scope` show the
 * EFFECTIVE (merged) tuple instead (BR-5, spec 0027) — `CampaignResource`
 * already resolves both server-side, so this view never special-cases
 * `derived_from_project`/`geo_locked_levels` beyond the inherited-levels hint.
 */
export function CampaignDetailView({ campaign }: CampaignDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(campaign.created_at)
  const geoPlace = campaign.geo_scope ? geoScopePlaceName(campaign.geo_scope, campaign) : null

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={campaign.name} icon={<Megaphone />} />}
        title={campaign.name}
        subtitle={campaign.code}
        badges={
          <>
            {campaign.derived_from_project ? (
              <Badge variant="secondary">{t('campaigns.detail.linkedToProject')}</Badge>
            ) : (
              <Badge variant="outline">{t('campaigns.detail.standalone')}</Badge>
            )}
            {campaign.geo_scope && geoPlace && (
              <GeoScopeBadge scope={campaign.geo_scope} place={geoPlace} />
            )}
          </>
        }
      />

      {campaign.description && (
        <DetailSection title={t('campaigns.form.description')}>
          <DetailGrid>
            <DetailField label={t('campaigns.form.description')} full>
              {campaign.description}
            </DetailField>
          </DetailGrid>
        </DetailSection>
      )}

      <DetailSection title={t('campaigns.form.sections.project.title')} icon={<FolderKanban />}>
        <DetailGrid>
          <DetailField label={t('campaigns.form.project')}>
            {campaign.project ? `${campaign.project.code} — ${campaign.project.name}` : <DetailEmpty />}
          </DetailField>
          <DetailField label={t('campaigns.form.partner')}>
            {campaign.partner?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('campaigns.form.operationalSite')}>
            {campaign.operational_site?.label ?? <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('campaigns.form.sections.classification.title')} icon={<Tags />}>
        <DetailGrid>
          <DetailField label={t('campaigns.form.status')}>
            {campaign.pipeline_status?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('campaigns.form.productLines')} full>
            <ProductLinesReadOnlyList lines={campaign.product_lines} />
          </DetailField>
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('campaigns.form.sections.geography.title')} icon={<Globe />}>
        <DetailGrid>
          <DetailField label={t('geo.country')}>{campaign.country?.name ?? <DetailEmpty />}</DetailField>
          <DetailField label={t('geo.state')}>{campaign.state?.name ?? <DetailEmpty />}</DetailField>
          <DetailField label={t('geo.province')}>{campaign.province?.name ?? <DetailEmpty />}</DetailField>
          <DetailField label={t('geo.city')}>{campaign.city?.name ?? <DetailEmpty />}</DetailField>
        </DetailGrid>
        {campaign.geo_locked_levels.length > 0 && (
          <p className="text-xs text-muted-foreground">{t('campaigns.form.geoInheritedFromProject')}</p>
        )}
      </DetailSection>

      <DetailSection title={t('campaigns.form.sections.planning.title')} icon={<CalendarRange />}>
        <DetailGrid>
          <DetailField label={t('campaigns.form.startDate')}>
            {formatDate(campaign.start_date) || <DetailEmpty />}
          </DetailField>
          <DetailField label={t('campaigns.form.endDate')}>
            {formatDate(campaign.end_date) || <DetailEmpty />}
          </DetailField>
          <DetailField label={t('campaigns.form.targetLead')}>
            {campaign.target_lead ?? <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('campaigns.detail.budget')} icon={<Wallet />}>
        <DetailGrid>
          <DetailField label={t('campaigns.form.totalBudget')}>
            {campaign.total_budget !== null ? formatDecimal(campaign.total_budget) : <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {campaign.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="campaigns" id={campaign.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('campaigns.columns.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
