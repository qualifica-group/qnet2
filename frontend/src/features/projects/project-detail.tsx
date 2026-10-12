import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import { RecordCanvas, RecordMeta } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { ProjectDetailHeader, ProjectStatsStrip } from '@/features/projects/project-detail-header'
import { ProjectDetailSections } from '@/features/projects/project-detail-sections'
import { useProjectInlineEdit } from '@/features/projects/use-project-inline-edit'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { ProjectDetailWithPermissions as ProjectDetailData } from '@/features/projects/types'

interface ProjectDetailViewProps {
  project: ProjectDetailData
  /** Called after an in-place save, so the host refreshes whatever lists the project (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single project, rendered as an enterprise-CRM record on the
 * same kit every detail uses: identity/KPI/sections card, the activity in the
 * side column, a metadata footer. Container-query driven (`RecordCanvas`) so
 * the same tree renders inside a resizable Sheet and on `/projects/:id`.
 *
 * There is no edit page (spec 0220): the sections' fields edit IN PLACE, one
 * at a time (`RecordInlineField`, driven by `useProjectInlineEdit`), each save
 * a PATCH of that field alone.
 */
export function ProjectDetailView(props: ProjectDetailViewProps) {
  // The edit form reads the field permissions while it is built, so the
  // provider wraps the whole detail, not just the sections.
  return (
    <ResourcePermissionsProvider permissions={props.project.permissions}>
      <ProjectDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function ProjectDetailContent({ project, onChanged }: ProjectDetailViewProps) {
  const { t } = useTranslation()
  const editor = useProjectInlineEdit(project, onChanged)
  const createdAt = formatDateTime(project.created_at)
  const collaborationTabs = project.permissions.actions.view_activity
    ? [activityLogTab('projects', project.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={collaborationTabs}
        header={
          <>
            <ProjectDetailHeader project={project} />
            <ProjectStatsStrip
              totalBudget={project.total_budget}
              allocatedBudget={project.allocated_budget}
              remainingBudget={project.remaining_budget}
              campaignsCount={project.campaigns_count}
            />
          </>
        }
      >
        {/* Provider only (no DOM): the inline editors share the edit form. */}
        <Form {...editor.form}>
          <ProjectDetailSections project={project} editor={editor} />
        </Form>
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
