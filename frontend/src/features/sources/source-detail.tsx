import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Database } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { RecordCanvas, RecordCardHeader, RecordMeta } from '@/components/detail/record-panel'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { SourceSections } from '@/features/sources/source-rows'
import { useSourceForm, type SourceFormValues } from '@/features/sources/use-source-form'
import type { SourceDetailWithPermissions, SourceFormMode } from '@/features/sources/types'
import { formatDateTime } from '@/features/table/cell-renderers'

const NO_CUSTOM_FIELDS = {}

interface SourceDetailViewProps {
  source: SourceDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single source on the record kit: identity band, the "Dettagli"
 * row and the custom fields, the activity in the side column, a metadata
 * footer. There is no edit page (wave 3 of the "no edit pages" plan): every
 * row edits IN PLACE, one at a time, each save a PATCH of that field alone.
 */
export function SourceDetailView(props: SourceDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.source.permissions}>
      <SourceDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function SourceDetailContent({ source, onChanged }: SourceDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<SourceFormMode>(() => ({ type: 'edit', source }), [source])
  const { form, onSubmit, serverError, clearServerError } = useSourceForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<SourceFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('sources.form.genericError'),
  })
  const createdAt = formatDateTime(source.created_at)
  const tabs: RecordCollaborationTab[] = source.permissions.actions.view_activity
    ? [activityLogTab('sources', source.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={source.name} icon={<Database />} />}
            title={source.name}
            legacyId={source.old_id}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <SourceSections
            form={form}
            inline={inline}
            canEdit={source.permissions.resource.update}
            customFieldValues={source.custom_fields ?? NO_CUSTOM_FIELDS}
          />
        </Form>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('sources.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
