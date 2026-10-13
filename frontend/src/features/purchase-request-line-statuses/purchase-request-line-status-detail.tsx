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
import { PurchaseRequestLineStatusRows } from '@/features/purchase-request-line-statuses/purchase-request-line-status-rows'
import type { PurchaseRequestLineStatusFormValues } from '@/features/purchase-request-line-statuses/purchase-request-line-status-schema'
import { usePurchaseRequestLineStatusForm } from '@/features/purchase-request-line-statuses/use-purchase-request-line-status-form'
import type { PurchaseRequestLineStatusDetailWithPermissions } from '@/features/purchase-request-line-statuses/types'

interface PurchaseRequestLineStatusDetailViewProps {
  purchaseRequestLineStatus: PurchaseRequestLineStatusDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record. */
  onChanged?: () => void
}

/**
 * Detail of a single line status on the record kit: identity band and the
 * in-place rows (name, color, icon). The system key and the order are shown
 * read-only; there is no create and no delete (spec 0248 D-2).
 */
export function PurchaseRequestLineStatusDetailView(props: PurchaseRequestLineStatusDetailViewProps) {
  return (
    <ResourcePermissionsProvider permissions={props.purchaseRequestLineStatus.permissions}>
      <PurchaseRequestLineStatusDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function PurchaseRequestLineStatusDetailContent({ purchaseRequestLineStatus, onChanged }: PurchaseRequestLineStatusDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  const { form, onSubmit, serverError, clearServerError } = usePurchaseRequestLineStatusForm({
    purchaseRequestLineStatus,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<PurchaseRequestLineStatusFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('purchaseRequestLineStatuses.form.genericError'),
  })
  const tabs: RecordCollaborationTab[] = purchaseRequestLineStatus.permissions.actions.view_activity
    ? [activityLogTab('purchase-request-line-statuses', purchaseRequestLineStatus.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={purchaseRequestLineStatus.name} icon={<ListChecks />} />}
            title={purchaseRequestLineStatus.name}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('purchaseRequestLineStatuses.form.sections.identity.title')} full>
              <RecordFieldList>
                <PurchaseRequestLineStatusRows inline={inline} canEdit={purchaseRequestLineStatus.permissions.resource.update} />
                <RecordField label={t('purchaseRequestLineStatuses.detail.system_key')}>{purchaseRequestLineStatus.system_key}</RecordField>
                <RecordField label={t('purchaseRequestLineStatuses.detail.sort_order')}>{purchaseRequestLineStatus.sort_order}</RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>
    </RecordCanvas>
  )
}
