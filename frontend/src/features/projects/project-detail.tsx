import { useTranslation } from 'react-i18next'
import { RecordCanvas, RecordCard, RecordMeta } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCollaborationCard } from '@/components/detail/record-collaboration-card'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
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
  const collaborationTabs = project.permissions.actions.view_activity
    ? [activityLogTab('projects', project.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        side={collaborationTabs.length > 0 ? <RecordCollaborationCard tabs={collaborationTabs} /> : null}
      >
        <RecordCard>
          <ProjectDetailHeader project={project} onEdit={onEdit} />
          <ProjectDetailStats project={project} />
          <ProjectDetailSections project={project} />
        </RecordCard>
      </RecordBody>

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
