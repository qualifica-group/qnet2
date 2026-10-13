import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { SlidersHorizontal } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCanvas, RecordCardHeader, RecordMeta, RecordSectionsGrid } from '@/components/detail/record-panel'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { AttributeSections } from '@/features/attributes/attribute-sections'
import { useAttributeForm, type AttributeFormValues } from '@/features/attributes/use-attribute-form'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { AttributeDetailWithPermissions, AttributeFormMode } from '@/features/attributes/types'

interface AttributeDetailViewProps {
  attribute: AttributeDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the attribute. */
  onChanged?: () => void
}

/**
 * Detail of a single attribute on the record kit every detail uses: identity
 * band, the rows and the type-dependent editors, the activity in the side
 * column, a metadata footer.
 *
 * There is no edit page (spec 0240): every row edits IN PLACE, one at a time,
 * each save a PATCH of that row's fields alone; code and type are fixed at
 * creation and show no pencil.
 */
export function AttributeDetailView(props: AttributeDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.attribute.permissions}>
      <AttributeDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function AttributeDetailContent({ attribute, onChanged }: AttributeDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<AttributeFormMode>(() => ({ type: 'edit', attribute }), [attribute])
  const { form, onSubmit, serverError, clearServerError } = useAttributeForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<AttributeFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('attributes.form.genericError'),
  })
  const createdAt = formatDateTime(attribute.created_at)
  const tabs: RecordCollaborationTab[] = attribute.permissions.actions.view_activity
    ? [activityLogTab('attributes', attribute.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={attribute.name} icon={<SlidersHorizontal />} />}
            title={attribute.name}
            legacyId={attribute.old_id}
            subtitle={attribute.code}
          />
        }
      >
        {/* Provider only (no DOM): the inline editors share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <AttributeSections
              form={form}
              inline={inline}
              creating={false}
              canEdit={attribute.permissions.resource.update}
            />
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('attributes.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
