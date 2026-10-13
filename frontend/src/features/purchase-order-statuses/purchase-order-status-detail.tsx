import { useTranslation } from 'react-i18next'
import { ListChecks } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import {
  RecordCanvas,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { PurchaseOrderStatusRows } from '@/features/purchase-order-statuses/purchase-order-status-rows'
import { usePurchaseOrderStatusForm } from '@/features/purchase-order-statuses/use-purchase-order-status-form'
import type { PurchaseOrderStatusFormValues } from '@/features/purchase-order-statuses/purchase-order-status-schema'
import {
  PURCHASE_ORDER_STATUSES_RESOURCE,
  type PurchaseOrderStatusDetailWithPermissions,
} from '@/features/purchase-order-statuses/types'

interface PurchaseOrderStatusDetailViewProps {
  status: PurchaseOrderStatusDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record. */
  onChanged?: () => void
}

/**
 * Detail of a single ODA status on the record kit: name, color and icon edit
 * IN PLACE (one row at a time, each save a PATCH of that field alone); the
 * enum key and the order are read-only. No delete, no edit page.
 */
export function PurchaseOrderStatusDetailView(props: PurchaseOrderStatusDetailViewProps) {
  return (
    <ResourcePermissionsProvider permissions={props.status.permissions}>
      <PurchaseOrderStatusDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function PurchaseOrderStatusDetailContent({ status, onChanged }: PurchaseOrderStatusDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  const { form, onSubmit, serverError, clearServerError } = usePurchaseOrderStatusForm({
    status,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<PurchaseOrderStatusFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('purchaseOrderStatuses.form.genericError'),
  })
  const tabs: RecordCollaborationTab[] = status.permissions.actions.view_activity
    ? [activityLogTab(PURCHASE_ORDER_STATUSES_RESOURCE, status.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader media={<DetailMonogram name={status.name} icon={<ListChecks />} />} title={status.name} />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('purchaseOrderStatuses.form.sections.identity.title')} full>
              <RecordFieldList>
                <PurchaseOrderStatusRows inline={inline} canEdit={status.permissions.resource.update} />
                <RecordField label={t('purchaseOrderStatuses.detail.systemKey')}>{status.system_key}</RecordField>
                <RecordField label={t('purchaseOrderStatuses.detail.sort_order')}>{status.sort_order}</RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>
    </RecordCanvas>
  )
}
