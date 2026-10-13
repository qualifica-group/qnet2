import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Flag } from 'lucide-react'
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
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import { formatDateTime } from '@/features/table/cell-renderers'
import { PipelineStatusRows } from '@/features/pipeline-statuses/pipeline-status-rows'
import { usePipelineStatusForm, type PipelineStatusFormValues } from '@/features/pipeline-statuses/use-pipeline-status-form'
import type { PipelineStatusDetailWithPermissions, PipelineStatusFormMode } from '@/features/pipeline-statuses/types'

interface PipelineStatusDetailViewProps {
  pipelineStatus: PipelineStatusDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single project status on the record kit: identity band, the
 * "Dettagli" rows, custom fields, the activity in the side column, a metadata
 * footer. There is no edit page (spec 0235): every row edits IN PLACE, one at
 * a time, each save a PATCH of that field alone.
 */
export function PipelineStatusDetailView(props: PipelineStatusDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.pipelineStatus.permissions}>
      <PipelineStatusDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function PipelineStatusDetailContent({ pipelineStatus, onChanged }: PipelineStatusDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<PipelineStatusFormMode>(() => ({ type: 'edit', pipelineStatus }), [pipelineStatus])
  const { form, onSubmit, serverError, clearServerError } = usePipelineStatusForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<PipelineStatusFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('pipelineStatuses.form.genericError'),
  })
  const canEdit = pipelineStatus.permissions.resource.update
  const createdAt = formatDateTime(pipelineStatus.created_at)
  const tabs = pipelineStatus.permissions.actions.view_activity
    ? [activityLogTab('pipeline-statuses', pipelineStatus.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader media={<DetailMonogram name={pipelineStatus.name} icon={<Flag />} />} title={pipelineStatus.name} />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('pipelineStatuses.form.sections.identity.title')} full>
              <RecordFieldList>
                <PipelineStatusRows inline={inline} canEdit={canEdit} isSystemRow={pipelineStatus.system_key !== null} />
                <RecordField label={t('pipelineStatuses.detail.sort_order')}>{pipelineStatus.sort_order}</RecordField>
              </RecordFieldList>
            </RecordSection>
            <CustomFieldRecordSections
              resource="pipeline-statuses"
              values={pipelineStatus.custom_fields ?? {}}
              inline={inline}
              control={form.control}
            />
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('pipelineStatuses.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
