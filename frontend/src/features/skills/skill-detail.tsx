import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Sparkles } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { formatDateTime } from '@/features/table/cell-renderers'
import { SkillRows } from '@/features/skills/skill-rows'
import { useSkillForm, type SkillFormValues } from '@/features/skills/use-skill-form'
import { SKILLS_DOMAIN, type SkillDetail, type SkillFormMode } from '@/features/skills/types'

interface SkillDetailViewProps {
  skill: SkillDetail
  /** Called after an in-place save, so the host refreshes whatever lists the record. */
  onChanged?: () => void
}

/**
 * Detail of a single skill on the record kit: identity band, the rows edited IN PLACE one at a
 * time (each save a PATCH of that field alone), the read-only count of people holding the skill, the activity log
 * and a metadata footer.
 */
export function SkillDetailView(props: SkillDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.skill.permissions}>
      <SkillDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function SkillDetailContent({ skill, onChanged }: SkillDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<SkillFormMode>(() => ({ type: 'edit', skill }), [skill])
  const { form, onSubmit, serverError, clearServerError } = useSkillForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<SkillFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('skills.form.genericError'),
  })
  const canEdit = skill.permissions.resource.update
  const createdAt = formatDateTime(skill.created_at)
  const updatedAt = formatDateTime(skill.updated_at)
  const tabs: RecordCollaborationTab[] = skill.permissions.actions.view_activity
    ? [activityLogTab(SKILLS_DOMAIN, skill.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={<RecordCardHeader media={<DetailMonogram name={skill.name} icon={<Sparkles />} />} title={skill.name} />}
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('skills.form.sections.identity.title')} full>
              <RecordFieldList>
                <SkillRows inline={inline} canEdit={canEdit} jobRoles={skill.job_roles} />
                <RecordField label={t('skills.detail.usersCount')}>{skill.users_count}</RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      {createdAt || updatedAt ? (
        <RecordMeta>
          {createdAt ? (
            <span>
              <span className="font-medium">{t('skills.detail.created_at')}</span>{' '}
              <span aria-hidden="true">·</span> {createdAt}
            </span>
          ) : null}
          {updatedAt ? (
            <span>
              <span className="font-medium">{t('skills.detail.updated_at')}</span>{' '}
              <span aria-hidden="true">·</span> {updatedAt}
            </span>
          ) : null}
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
