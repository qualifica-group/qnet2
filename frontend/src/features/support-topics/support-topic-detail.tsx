import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { LifeBuoy } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import {
  RecordCanvas,
  RecordCardHeader,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { formatDateTime } from '@/features/table/cell-renderers'
import { SupportTopicRows } from '@/features/support-topics/support-topic-rows'
import { useSupportTopicForm } from '@/features/support-topics/use-support-topic-form'
import type { SupportTopicFormValues } from '@/features/support-topics/support-topic-schema'
import type { SupportTopicDetailWithPermissions, SupportTopicFormMode } from '@/features/support-topics/types'

interface SupportTopicDetailViewProps {
  supportTopic: SupportTopicDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record. */
  onChanged?: () => void
}

/** Detail of a support topic: every row edits in place, one at a time, each save a PATCH of that field. */
export function SupportTopicDetailView(props: SupportTopicDetailViewProps) {
  return (
    <ResourcePermissionsProvider permissions={props.supportTopic.permissions}>
      <SupportTopicDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function SupportTopicDetailContent({ supportTopic, onChanged }: SupportTopicDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  const mode = useMemo<SupportTopicFormMode>(() => ({ type: 'edit', supportTopic }), [supportTopic])
  const { form, onSubmit, serverError, clearServerError } = useSupportTopicForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<SupportTopicFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('supportTopics.form.genericError'),
  })
  const createdAt = formatDateTime(supportTopic.created_at)
  const updatedAt = formatDateTime(supportTopic.updated_at)
  const tabs: RecordCollaborationTab[] = supportTopic.permissions.actions.view_activity
    ? [activityLogTab('support-topics', supportTopic.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={supportTopic.name} icon={<LifeBuoy />} />}
            title={supportTopic.name}
          />
        }
      >
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('supportTopics.form.sectionTitle')} full>
              <RecordFieldList>
                <SupportTopicRows
                  inline={inline}
                  canEdit={supportTopic.permissions.resource.update}
                  supportTopic={supportTopic}
                />
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('supportTopics.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('supportTopics.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
