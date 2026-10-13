import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Tag as TagIcon } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { RecordCanvas, RecordCardHeader, RecordMeta } from '@/components/detail/record-panel'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { TagSections } from '@/features/tags/tag-rows'
import { useTagForm, type TagFormValues } from '@/features/tags/use-tag-form'
import type { TagDetailWithPermissions, TagFormMode } from '@/features/tags/types'
import { formatDateTime } from '@/features/table/cell-renderers'

const NO_CUSTOM_FIELDS = {}

interface TagDetailViewProps {
  tag: TagDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single tag on the record kit: identity band, the "Dettagli"
 * row and the custom fields, the activity in the side column, a metadata
 * footer. There is no edit page (wave 3 of the "no edit pages" plan): every
 * row edits IN PLACE, one at a time, each save a PATCH of that field alone.
 */
export function TagDetailView(props: TagDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.tag.permissions}>
      <TagDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function TagDetailContent({ tag, onChanged }: TagDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<TagFormMode>(() => ({ type: 'edit', tag }), [tag])
  const { form, onSubmit, serverError, clearServerError } = useTagForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<TagFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('tags.form.genericError'),
  })
  const createdAt = formatDateTime(tag.created_at)
  const tabs: RecordCollaborationTab[] = tag.permissions.actions.view_activity
    ? [activityLogTab('tags', tag.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={tag.name} icon={<TagIcon />} />}
            title={tag.name}
            legacyId={tag.old_id}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <TagSections
            form={form}
            inline={inline}
            canEdit={tag.permissions.resource.update}
            customFieldValues={tag.custom_fields ?? NO_CUSTOM_FIELDS}
          />
        </Form>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('tags.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
