import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { ArrowRight, Pencil } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { cn } from '@/lib/utils'
import { statusBadgeClassName } from '@/features/projects/status-badge-classes'
import { GeoScopeBadge } from '@/features/geo/geo-scope-badge'
import type { ProjectCard as ProjectCardData } from '@/features/projects/types'

interface ProjectCardProps {
  project: ProjectCardData
  onEdit: (id: number) => void
}

/** A single stat cell inside a card (campaigns / leads). */
function CardStat({ label, value }: { label: string; value: number | string }) {
  return (
    <div className="flex flex-col items-center gap-0.5 rounded-md bg-muted/50 py-1.5">
      <span className="text-sm font-semibold tabular-nums">{value}</span>
      <span className="text-[10px] uppercase tracking-wide text-muted-foreground">{label}</span>
    </div>
  )
}

/**
 * A single project card in the card grid (spec 0026 AC-009): code, status
 * badge, name, the 2 stats and an "Open detail" link. The edit affordance —
 * it opens the project's detail, which edits in place (spec 0220) — only
 * renders when `project.can.update` (server-computed, BR-2): UI hides, the
 * backend re-authorizes the actual mutation.
 */
export function ProjectCard({ project, onEdit }: ProjectCardProps) {
  const { t } = useTranslation()
  const colorClass = project.pipeline_status ? statusBadgeClassName(project.pipeline_status.color) : undefined

  return (
    <Card className="gap-3 py-3">
      <CardContent className="flex flex-col gap-3 px-3">
        <div className="flex items-start justify-between gap-2">
          <div className="flex min-w-0 flex-col gap-1">
            <span className="truncate font-mono text-xs text-muted-foreground">{project.code}</span>
            {project.pipeline_status ? (
              <Badge variant="secondary" className={cn('w-fit', colorClass)}>
                {project.pipeline_status.name}
              </Badge>
            ) : null}
          </div>
          {project.can.update ? (
            <Button
              type="button"
              variant="ghost"
              size="icon-sm"
              aria-label={t('projects.grid.editAction')}
              onClick={() => onEdit(project.id)}
            >
              <Pencil aria-hidden="true" />
            </Button>
          ) : null}
        </div>

        <h3 className="truncate text-sm font-semibold" title={project.name}>
          {project.name}
        </h3>

        {project.geo_scope && project.geo_label ? (
          <GeoScopeBadge scope={project.geo_scope} place={project.geo_label} />
        ) : null}

        <div className="grid grid-cols-2 gap-1.5">
          <CardStat label={t('projects.grid.stats.campaigns')} value={project.campaigns_count} />
          <CardStat label={t('projects.grid.stats.leads')} value={project.leads_count} />
        </div>

        <Link
          to={`/projects/${project.id}`}
          className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
        >
          {t('projects.grid.openDetail')}
          <ArrowRight className="size-3.5" aria-hidden="true" />
        </Link>
      </CardContent>
    </Card>
  )
}
