import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import { Network } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { RecordCanvas, RecordCardHeader, RecordMeta } from '@/components/detail/record-panel'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { persistedBusinessFunctionValues } from '@/features/business-functions/business-function-record'
import { BusinessFunctionSections } from '@/features/business-functions/business-function-rows'
import { useBusinessFunctionForm, type BusinessFunctionFormValues } from '@/features/business-functions/use-business-function-form'
import type {
  BusinessFunctionDetail,
  BusinessFunctionDetailWithPermissions,
  BusinessFunctionFormMode,
} from '@/features/business-functions/types'
import { formatDateTime } from '@/features/table/cell-renderers'

interface BusinessFunctionDetailViewProps {
  businessFunction: BusinessFunctionDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single business function on the record kit: identity band (type badge), the
 * "Dettagli", assignment and locations rows and the custom fields, the activity in the side column, a metadata
 * footer. There is no edit page (wave 3 of the "no edit pages" plan): every
 * row edits IN PLACE, one at a time, each save a PATCH of that field alone.
 */
export function BusinessFunctionDetailView(props: BusinessFunctionDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.businessFunction.permissions}>
      <BusinessFunctionDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function BusinessFunctionDetailContent({ businessFunction, onChanged }: BusinessFunctionDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<BusinessFunctionFormMode>(() => ({ type: 'edit', businessFunction }), [businessFunction])
  const { form, onSubmit, serverError, clearServerError } = useBusinessFunctionForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<BusinessFunctionFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('businessFunctions.form.genericError'),
  })
  const createdAt = formatDateTime(businessFunction.created_at)
  const tabs: RecordCollaborationTab[] = businessFunction.permissions.actions.view_activity
    ? [activityLogTab('business-functions', businessFunction.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={businessFunction.name} icon={<Network />} />}
            title={businessFunction.name}
            legacyId={businessFunction.old_id}
            badges={<Badge variant="secondary">{typeLabel(t, businessFunction.type)}</Badge>}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <BusinessFunctionSections
            form={form}
            inline={inline}
            canEdit={businessFunction.permissions.resource.update}
            values={persistedBusinessFunctionValues(businessFunction)}
          />
        </Form>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('businessFunctions.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}

/** Maps the mutually-exclusive `type` to its localized label. */
function typeLabel(t: TFunction, type: BusinessFunctionDetail['type']): string {
  if (type === 'business_unit') {
    return t('businessFunctions.form.type.businessUnit')
  }
  if (type === 'business_service') {
    return t('businessFunctions.form.type.businessService')
  }
  return t('businessFunctions.form.type.none')
}
