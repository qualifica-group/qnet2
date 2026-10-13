import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { SignalHigh } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import {
  RecordCanvas,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { formatDateTime } from '@/features/table/cell-renderers'
import { PurchaseRequestPriorityRows } from '@/features/purchase-request-priorities/purchase-request-priority-rows'
import { usePurchaseRequestPriorityForm, type PurchaseRequestPriorityFormValues } from '@/features/purchase-request-priorities/use-purchase-request-priority-form'
import type { PurchaseRequestPriorityDetailWithPermissions, PurchaseRequestPriorityFormMode } from '@/features/purchase-request-priorities/types'

interface PurchaseRequestPriorityDetailViewProps {
  purchaseRequestPriority: PurchaseRequestPriorityDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single RDA priority on the record kit: identity band, the
 * "Dettagli" rows, the activity in the side column, a metadata footer. There
 * is no edit page (wave 3 of the "no edit pages" plan): every row edits IN
 * PLACE, one at a time, each save a PATCH of that field alone.
 */
export function PurchaseRequestPriorityDetailView(props: PurchaseRequestPriorityDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.purchaseRequestPriority.permissions}>
      <PurchaseRequestPriorityDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function PurchaseRequestPriorityDetailContent({ purchaseRequestPriority, onChanged }: PurchaseRequestPriorityDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<PurchaseRequestPriorityFormMode>(() => ({ type: 'edit', purchaseRequestPriority }), [purchaseRequestPriority])
  const { form, onSubmit, serverError, clearServerError } = usePurchaseRequestPriorityForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<PurchaseRequestPriorityFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('purchaseRequestPriorities.form.genericError'),
  })
  const canEdit = purchaseRequestPriority.permissions.resource.update
  const createdAt = formatDateTime(purchaseRequestPriority.created_at)
  const updatedAt = formatDateTime(purchaseRequestPriority.updated_at)
  const tabs: RecordCollaborationTab[] = purchaseRequestPriority.permissions.actions.view_activity
    ? [activityLogTab('purchase-request-priorities', purchaseRequestPriority.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={purchaseRequestPriority.name} icon={<SignalHigh />} />}
            title={purchaseRequestPriority.name}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('purchaseRequestPriorities.form.sections.identity.title')} full>
              <RecordFieldList>
                <PurchaseRequestPriorityRows inline={inline} canEdit={canEdit} />
                <RecordField label={t('purchaseRequestPriorities.detail.sort_order')}>{purchaseRequestPriority.sort_order}</RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('purchaseRequestPriorities.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('purchaseRequestPriorities.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
