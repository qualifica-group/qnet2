import { useMemo } from 'react'
import { CircleDollarSign } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCanvas, RecordCardHeader, RecordMeta } from '@/components/detail/record-panel'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { formatDateTime } from '@/features/table/cell-renderers'
import { CommissionConfigurationSections } from './commission-configuration-rows'
import type { CommissionConfigurationFormValues } from './commission-configuration-schema'
import { useCommissionConfigurationForm } from './use-commission-configuration-form'
import type {
  CommissionConfigurationDetailWithPermissions,
  CommissionConfigurationFormMode,
  CommissionConfigurationRefs,
} from './types'

interface CommissionConfigurationDetailViewProps {
  configuration: CommissionConfigurationDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single commission configuration on the record kit: identity
 * band, the three sections of rows, the activity card, a metadata footer.
 * There is no edit page (wave 4 of the "no edit pages" plan): every row edits
 * IN PLACE, one at a time, each save a PATCH of that row's field(s) alone;
 * the fields the server validates together are grouped in one row.
 */
export function CommissionConfigurationDetailView(props: CommissionConfigurationDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.configuration.permissions}>
      <CommissionConfigurationDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function CommissionConfigurationDetailContent({ configuration, onChanged }: CommissionConfigurationDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<CommissionConfigurationFormMode>(() => ({ type: 'edit', configuration }), [configuration])
  const { form, onSubmit, serverError, clearServerError } = useCommissionConfigurationForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<CommissionConfigurationFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('commissionConfigurations.form.genericError'),
  })
  const refs = useMemo<CommissionConfigurationRefs>(
    () => ({
      category: configuration.product_category,
      product: configuration.product,
      recipient: configuration.recipient ?? null,
    }),
    [configuration],
  )
  const permissions = configuration.permissions
  const title = permissions.fields.name?.visible === false ? t('commissionConfigurations.detail.title') : configuration.name

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={
          permissions.actions.view_activity
            ? [activityLogTab('commission-configurations', configuration.id, t('activityLog.title'))]
            : []
        }
        header={
          <RecordCardHeader
            media={<DetailMonogram name={title} icon={<CircleDollarSign />} />}
            title={title}
            legacyId={configuration.old_id}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <CommissionConfigurationSections inline={inline} canEdit={permissions.resource.update} refs={refs} />
        </Form>
      </RecordBody>

      {permissions.fields.updated_at?.visible !== false ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('commissionConfigurations.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {formatDateTime(configuration.updated_at)}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
