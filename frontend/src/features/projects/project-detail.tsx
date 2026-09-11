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
import { ProjectDetailHeader, ProjectDetailStats } from '@/features/projects/project-detail-header'
import { ProjectDetailSections } from '@/features/projects/project-detail-sections'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { ProjectDetailWithPermissions as ProjectDetailData } from '@/features/projects/types'

interface ProjectDetailViewProps {
  project: ProjectDetailData
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single project, rendered as an enterprise-CRM record on
 * the same kit Opportunita', Utenti, Lead and Campagne use: the
 * identity/KPI/sections card on the left, the activity card on the right, a
 * metadata footer. Container-query driven (`RecordCanvas`) so the same tree
 * renders correctly both inside a resizable Sheet and on the full-bleed
 * `/projects/:id` page.
 */
export function ProjectDetailView({ project, onEdit }: ProjectDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(project.created_at)
  const canViewActivity = project.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <div className={cn(RECORD_BODY_GRID_CLASS, canViewActivity && RECORD_BODY_WITH_SIDE_CLASS)}>
        <div className={RECORD_COLUMN_CLASS}>
          <RecordCard>
            <ProjectDetailHeader project={project} onEdit={onEdit} />
            <ProjectDetailStats project={project} />
            <ProjectDetailSections project={project} />
          </RecordCard>
        </div>

        {canViewActivity ? (
          <div className={RECORD_COLUMN_CLASS}>
            <RecordCard className="p-4">
              <RecordSection title={t('activityLog.title')} icon={<History />}>
                <ActivityLogSection resource="projects" id={project.id} />
              </RecordSection>
            </RecordCard>
          </div>
        ) : null}
      </div>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('projects.columns.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
