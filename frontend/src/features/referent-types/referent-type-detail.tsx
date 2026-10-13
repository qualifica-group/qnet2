import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Tag } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { RecordCanvas, RecordCardHeader, RecordMeta } from '@/components/detail/record-panel'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { ReferentTypeSections } from '@/features/referent-types/referent-type-rows'
import { useReferentTypeForm, type ReferentTypeFormValues } from '@/features/referent-types/use-referent-type-form'
import type { ReferentTypeDetailWithPermissions, ReferentTypeFormMode } from '@/features/referent-types/types'
import { formatDateTime } from '@/features/table/cell-renderers'

const NO_CUSTOM_FIELDS = {}

interface ReferentTypeDetailViewProps {
  referentType: ReferentTypeDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single referent type on the record kit: identity band, the "Dettagli"
 * row and the custom fields, the activity in the side column, a metadata
 * footer. There is no edit page (wave 3 of the "no edit pages" plan): every
 * row edits IN PLACE, one at a time, each save a PATCH of that field alone.
 */
export function ReferentTypeDetailView(props: ReferentTypeDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.referentType.permissions}>
      <ReferentTypeDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function ReferentTypeDetailContent({ referentType, onChanged }: ReferentTypeDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<ReferentTypeFormMode>(() => ({ type: 'edit', referentType }), [referentType])
  const { form, onSubmit, serverError, clearServerError } = useReferentTypeForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<ReferentTypeFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('referentTypes.form.genericError'),
  })
  const createdAt = formatDateTime(referentType.created_at)
  const tabs: RecordCollaborationTab[] = referentType.permissions.actions.view_activity
    ? [activityLogTab('referent-types', referentType.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={referentType.name} icon={<Tag />} />}
            title={referentType.name}
            legacyId={referentType.old_id}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <ReferentTypeSections
            form={form}
            inline={inline}
            canEdit={referentType.permissions.resource.update}
            customFieldValues={referentType.custom_fields ?? NO_CUSTOM_FIELDS}
          />
        </Form>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('referentTypes.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
