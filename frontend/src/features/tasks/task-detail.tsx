import { useTranslation } from 'react-i18next'
import { CalendarClock, ClipboardList, Contact, Link2, MessageSquareWarning, Users } from 'lucide-react'
import { formatDate } from '@/lib/formatting/date-display'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RichTextContent } from '@/components/rich-text/rich-text-content'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCollaborationCard } from '@/components/detail/record-collaboration-card'
import {
  RecordCanvas,
  RecordCard,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import { TaskActionsBar } from '@/features/tasks/task-actions-bar'
import { useTaskCollaborationTabs } from '@/features/tasks/task-collaboration-section'
import { TaskDetailHeader, TaskDetailStats } from '@/features/tasks/task-detail-header'
import { TaskPeopleList, TaskPerson } from '@/features/tasks/task-people-list'
import { TaskSubtasksSection } from '@/features/tasks/task-subtasks-section'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'

interface TaskDetailViewProps {
  task: TaskDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
  /** Opens a child task's own detail (AC-085). */
  onOpenSubtask: (subtaskId: number) => void
  /** Opens the create form with this task prefilled and locked as the parent (AC-085). */
  onCreateSubtask: () => void
}

/**
 * Read-only detail of a single task, on the same `RecordCanvas` kit as the
 * Opportunita'/Commessa records: identity band with the "Modifica" action on
 * the card (`TaskDetailHeader`), domain actions, KPI strip, then the sections.
 *
 * Spec 0117/0134 D-3: note, documenti, log attivita' e segnatempo vivono
 * nella card di collaborazione, nella colonna laterale (Opportunita' reference
 * layout), non piu' sotto la card principale.
 */
export function TaskDetailView({ task, onEdit, onOpenSubtask, onCreateSubtask }: TaskDetailViewProps) {
  const { t } = useTranslation()
  const collaborationTabs = useTaskCollaborationTabs(task)
  const createdAt = formatDateTime(task.created_at)
  const updatedAt = formatDateTime(task.updated_at)

  return (
    <RecordCanvas>
      <RecordBody
        side={collaborationTabs.length > 0 ? <RecordCollaborationCard tabs={collaborationTabs} /> : null}
      >
        <RecordCard>
          <TaskDetailHeader task={task} onEdit={onEdit} />

          <TaskActionsBar task={task} />

          <TaskDetailStats task={task} />

          <RecordSectionsGrid>
            <RecordSection title={t('tasks.detail.sections.identity')} icon={<ClipboardList />}>
              <RecordFieldList>
                <RecordField label={t('tasks.detail.isBlocked')}>
                  {t(task.is_blocked ? 'common.yes' : 'common.no')}
                </RecordField>
                <RecordField label={t('tasks.detail.description')}>
                  {task.description ? <RichTextContent html={task.description} /> : <DetailEmpty />}
                </RecordField>
              </RecordFieldList>
            </RecordSection>

            <RecordSection title={t('tasks.detail.sections.people')} icon={<Users />}>
              <RecordFieldList>
                <RecordField label={t('tasks.detail.creator')}>
                  <TaskPerson person={task.creator} />
                </RecordField>
                <RecordField label={t('tasks.detail.requester')}>
                  {task.requester ? <TaskPerson person={task.requester} /> : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('tasks.detail.assignees')}>
                  <TaskPeopleList people={task.assignees} />
                </RecordField>
                <RecordField label={t('tasks.detail.watchers')}>
                  <TaskPeopleList people={task.watchers} />
                </RecordField>
              </RecordFieldList>
            </RecordSection>

            <RecordSection title={t('tasks.detail.sections.planning')} icon={<CalendarClock />}>
              <RecordFieldList>
                <RecordField label={t('tasks.detail.completionDate')}>
                  {formatDate(task.completion_date) || <DetailEmpty />}
                </RecordField>
                <RecordField label={t('tasks.detail.startTime')}>
                  {task.start_time ?? <DetailEmpty />}
                </RecordField>
                <RecordField label={t('tasks.detail.endTime')}>
                  {task.end_time ?? <DetailEmpty />}
                </RecordField>
              </RecordFieldList>
            </RecordSection>

            <RecordSection title={t('tasks.detail.sections.links')} icon={<Link2 />}>
              <RecordFieldList>
                <RecordField label={t('tasks.detail.registry')} icon={<Contact />}>
                  {task.registry ? (
                    <RecordLink domain="registries" id={task.registry.id}>
                      {task.registry.name}
                    </RecordLink>
                  ) : (
                    <DetailEmpty />
                  )}
                </RecordField>
                <RecordField label={t('tasks.detail.referent')}>
                  {task.referent ? (
                    <RecordLink domain="referents" id={task.referent.id}>
                      {task.referent.name}
                    </RecordLink>
                  ) : (
                    <DetailEmpty />
                  )}
                </RecordField>
                <RecordField label={t('tasks.detail.opportunity')}>
                  {task.opportunity ? (
                    <RecordLink domain="opportunities" id={task.opportunity.id}>
                      {task.opportunity.name}
                    </RecordLink>
                  ) : (
                    <DetailEmpty />
                  )}
                </RecordField>
                <RecordField label={t('tasks.detail.workOrder')}>
                  {task.work_order ? (
                    <RecordLink domain="work-orders" id={task.work_order.id}>
                      {`${task.work_order.code} — ${task.work_order.title}`}
                    </RecordLink>
                  ) : (
                    <DetailEmpty />
                  )}
                </RecordField>
              </RecordFieldList>
            </RecordSection>

            {task.requires_closure_feedback || task.requires_validation ? (
              <RecordSection title={t('tasks.detail.sections.closure')} icon={<MessageSquareWarning />} full>
                <RecordFieldList>
                  <RecordField label={t('tasks.detail.requiresClosureFeedback')}>
                    {t(task.requires_closure_feedback ? 'common.yes' : 'common.no')}
                  </RecordField>
                  <RecordField label={t('tasks.detail.requiresValidation')}>
                    {t(task.requires_validation ? 'common.yes' : 'common.no')}
                  </RecordField>
                  <RecordField label={t('tasks.detail.closureFeedback')}>
                    {task.closure_feedback ? (
                      <span className="whitespace-pre-wrap">{task.closure_feedback}</span>
                    ) : (
                      <DetailEmpty />
                    )}
                  </RecordField>
                </RecordFieldList>
              </RecordSection>
            ) : null}

            <TaskSubtasksSection
              subtasks={task.subtasks}
              onOpen={onOpenSubtask}
              onCreate={onCreateSubtask}
              canCreateSubtask={task.permissions.actions.create_subtask}
            />
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('tasks.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('tasks.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
