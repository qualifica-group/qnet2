import { useTranslation } from 'react-i18next'
import { FolderKanban, Megaphone, Pencil, Wallet } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader, RecordStat, RecordStatStrip } from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Progress } from '@/components/ui/progress'
import { cn } from '@/lib/utils'
import { GeoScopeBadge } from '@/features/geo/geo-scope-badge'
import { geoScopePlaceName } from '@/features/geo/geo-scope'
import { formatDecimal } from '@/features/products/column-renderers'
import { statusBadgeClassName } from '@/features/projects/status-badge-classes'
import type { ProjectDetailWithPermissions as ProjectDetailData } from '@/features/projects/types'

/**
 * Identity band and KPI strip of the project record card. Kept in one file:
 * both pieces read the same handful of top-level fields and are always mounted
 * together — the same split `opportunity-detail-header.tsx` makes.
 */

/** Allocation at or above this share of the total budget is no longer "healthy". */
const BUDGET_WARNING_RATIO = 0.9

interface ProjectDetailHeaderProps {
  project: ProjectDetailData
  /** Opens the module's existing edit surface; absent = no edit affordance. */
  onEdit?: () => void
}

/** Identity band: monogram, name, code subtitle, pipeline status and geographic scope. */
export function ProjectDetailHeader({ project, onEdit }: ProjectDetailHeaderProps) {
  const { t } = useTranslation()
  const canEdit = Boolean(onEdit) && project.permissions.resource.update
  const geoPlace = project.geo_scope
    ? geoScopePlaceName(project.geo_scope, {
        country: project.country,
        state: project.state,
        province: project.province,
        city: project.city,
      })
    : null

  return (
    <RecordCardHeader
      media={
        <DetailMonogram
          name={project.name}
          icon={<FolderKanban />}
          className="size-10 text-base [&>svg]:size-5"
        />
      }
      title={project.name}
      subtitle={project.code}
      badges={
        <>
          <Badge
            variant="secondary"
            className={cn(statusBadgeClassName(project.pipeline_status.color))}
          >
            {project.pipeline_status.name}
          </Badge>
          {project.geo_scope && geoPlace ? (
            <GeoScopeBadge scope={project.geo_scope} place={geoPlace} />
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

interface ProjectDetailStatsProps {
  project: ProjectDetailData
}

/**
 * KPI strip: the BR-7 budget triplet plus how many campaigns hang off the
 * project. The allocated tile carries the share bar — "how much of this budget
 * is already committed" is the one question the three raw numbers make you do
 * arithmetic for.
 *
 * The count is a number, not a link: the list screens read no filters from the
 * URL, so a link would land on every campaign rather than this project's
 * (user decision 2026-09-11).
 */
export function ProjectDetailStats({ project }: ProjectDetailStatsProps) {
  const { t } = useTranslation()
  const share = allocationShare(project.total_budget, project.allocated_budget)

  return (
    <RecordStatStrip>
      <RecordStat
        label={t('projects.form.totalBudget')}
        icon={<Wallet />}
        value={project.total_budget !== null ? formatDecimal(project.total_budget) : <DetailEmpty />}
      />
      <RecordStat
        label={t('projects.detail.allocatedBudget')}
        value={
          <span className="flex items-center gap-2">
            <span className="tabular-nums">{formatDecimal(project.allocated_budget)}</span>
            {share !== null ? (
              <Progress
                value={Math.min(share, 100)}
                size="xs"
                aria-hidden="true"
                className="w-16 shrink-0"
                indicatorClassName={allocationToneClass(share)}
              />
            ) : null}
          </span>
        }
        hint={share !== null ? t('projects.detail.allocatedShare', { share }) : undefined}
      />
      <RecordStat
        label={t('projects.detail.remainingBudget')}
        value={
          project.remaining_budget !== null ? (
            formatDecimal(project.remaining_budget)
          ) : (
            <DetailEmpty />
          )
        }
      />
      <RecordStat
        label={t('projects.detail.campaignsLabel')}
        icon={<Megaphone />}
        value={project.campaigns_count}
      />
    </RecordStatStrip>
  )
}

/**
 * Allocated as a whole-number percentage of the total, or null when there is no
 * total to measure against (A-1: `total_budget` unset means there is no residual
 * either). A zero total would divide by zero, so it answers null too.
 */
function allocationShare(totalBudget: string | null, allocatedBudget: string): number | null {
  const total = totalBudget === null ? null : Number(totalBudget)

  if (total === null || !Number.isFinite(total) || total <= 0) {
    return null
  }

  return Math.round((Number(allocatedBudget) / total) * 100)
}

/** Green while there is room, amber as the budget fills, destructive once it is exceeded. */
function allocationToneClass(share: number): string {
  if (share > 100) {
    return 'bg-destructive'
  }
  return share >= BUDGET_WARNING_RATIO * 100 ? 'bg-amber-500' : 'bg-emerald-500'
}
