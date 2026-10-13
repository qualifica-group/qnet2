import { useTranslation } from 'react-i18next'
import { Puzzle } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCanvas, RecordCardHeader, RecordMeta, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { CustomFieldDefinitionSections } from '@/features/custom-fields/custom-field-definition-sections'
import { useCustomFieldDefinitionInlineEdit } from '@/features/custom-fields/use-custom-field-definition-inline-edit'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { CustomFieldDefinitionDetailWithPermissions } from '@/features/custom-fields/types'

interface CustomFieldDetailViewProps {
  definition: CustomFieldDefinitionDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the definition. */
  onChanged?: () => void
}

/**
 * Detail of a single custom field definition on the record kit every detail
 * uses: identity band, the rows and the type-dependent editors, the activity
 * in the side column, a metadata footer.
 *
 * There is no edit page (spec 0231): every row edits IN PLACE, one at a time
 * (`useCustomFieldDefinitionInlineEdit`); module, type and key are fixed at
 * creation and show no pencil.
 */
export function CustomFieldDetailView(props: CustomFieldDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.definition.permissions}>
      <CustomFieldDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function CustomFieldDetailContent({ definition, onChanged }: CustomFieldDetailViewProps) {
  const { t } = useTranslation()
  const { form, inline } = useCustomFieldDefinitionInlineEdit(definition, onChanged)
  const createdAt = formatDateTime(definition.created_at)
  const collaborationTabs = definition.permissions.actions.view_activity
    ? [activityLogTab('custom-fields', definition.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={collaborationTabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={definition.label} icon={<Puzzle />} />}
            title={definition.label}
            subtitle={definition.key}
          />
        }
      >
        {/* Provider only (no DOM): the inline editors share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <CustomFieldDefinitionSections
              form={form}
              inline={inline}
              creating={false}
              canEdit={definition.permissions.resource.update}
            />
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('customFields.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
