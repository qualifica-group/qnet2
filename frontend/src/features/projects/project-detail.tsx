import { useTranslation } from 'react-i18next'
import { AlertTriangle, CalendarRange, FolderKanban, Globe, History, Tags, Wallet } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
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
import type { ProjectDetailWithPermissions as ProjectDetailData } from '@/features/projects/types'

interface ProjectDetailViewProps {
  project: ProjectDetailData
}

/**
 * Budget over-allocation warning (BR-7, AC-044): shown only when
 * `remaining_budget` parses to a negative number. `remaining_budget` is
 * `null` when the project has no `total_budget` set (A-1) — no warning in
 * that case, since there is no residual to check.
 */
function BudgetOverallocationWarning({ remainingBudget }: { remainingBudget: string | null }) {
  const { t } = useTranslation()
  const remaining = remainingBudget === null ? null : Number(remainingBudget)
  const isOverallocated = remaining !== null && remaining < 0

  return isOverallocated ? (
    <div
      role="alert"
      className="flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
    >
      <AlertTriangle className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
      <span>
        {t('projects.detail.overallocatedWarning', { amount: formatDecimal(Math.abs(remaining)) })}
      </span>
    </div>
  ) : null
}

/**
 * Read-only detail of a single project, fetched fresh from the
 * (re-authorized) detail endpoint. Composed from the shared detail kit;
 * rendered by the dedicated detail page (spec 0023, mirrors 0022).
 */
export function ProjectDetailView({ project }: ProjectDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(project.created_at)
  const geoPlaceName = project.geo_scope
    ? geoScopePlaceName(project.geo_scope, {
        country: project.country,
        state: project.state,
        province: project.province,
        city: project.city,
      })
    : null

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={project.name} icon={<FolderKanban />} />}
        title={project.name}
        subtitle={project.code}
        badges={
          <>
            <Badge variant="secondary">
              {t('projects.detail.campaignsCount', { count: project.campaigns_count })}
            </Badge>
            {project.geo_scope && geoPlaceName ? (
              <GeoScopeBadge scope={project.geo_scope} place={geoPlaceName} />
            ) : null}
          </>
        }
      />

      {project.description && (
        <DetailSection title={t('projects.form.description')}>
          <DetailGrid>
            <DetailField label={t('projects.form.description')} full>
              {project.description}
            </DetailField>
          </DetailGrid>
        </DetailSection>
      )}

      <DetailSection title={t('projects.form.sections.classification.title')} icon={<Tags />}>
        <DetailGrid>
          <DetailField label={t('projects.form.status')}>{project.pipeline_status.name}</DetailField>
          <DetailField label={t('projects.form.businessFunction')}>
            {project.business_function?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('projects.form.productCategory')}>
            {project.product_category?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('projects.form.partner')}>
            {project.partner?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('projects.form.operationalSite')}>
            {project.operational_site?.label ?? <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('projects.form.sections.geography.title')} icon={<Globe />}>
        <DetailGrid>
          <DetailField label={t('geo.country')}>{project.country?.name ?? <DetailEmpty />}</DetailField>
          <DetailField label={t('geo.state')}>{project.state?.name ?? <DetailEmpty />}</DetailField>
          <DetailField label={t('geo.province')}>{project.province?.name ?? <DetailEmpty />}</DetailField>
          <DetailField label={t('geo.city')}>{project.city?.name ?? <DetailEmpty />}</DetailField>
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('projects.form.sections.planning.title')} icon={<CalendarRange />}>
        <DetailGrid>
          <DetailField label={t('projects.form.startDate')}>
            {formatDate(project.start_date) || <DetailEmpty />}
          </DetailField>
          <DetailField label={t('projects.form.endDate')}>
            {formatDate(project.end_date) || <DetailEmpty />}
          </DetailField>
          <DetailField label={t('projects.form.targetLead')}>
            {project.target_lead ?? <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('projects.detail.budget')} icon={<Wallet />}>
        <div className={cn('flex flex-col gap-4')}>
          <DetailGrid>
            <DetailField label={t('projects.form.totalBudget')}>
              {project.total_budget !== null ? formatDecimal(project.total_budget) : <DetailEmpty />}
            </DetailField>
            <DetailField label={t('projects.detail.allocatedBudget')}>
              {formatDecimal(project.allocated_budget)}
            </DetailField>
            <DetailField label={t('projects.detail.remainingBudget')}>
              {project.remaining_budget !== null ? formatDecimal(project.remaining_budget) : <DetailEmpty />}
            </DetailField>
          </DetailGrid>
          <BudgetOverallocationWarning remainingBudget={project.remaining_budget} />
        </div>
      </DetailSection>

      {project.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="projects" id={project.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('projects.columns.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
