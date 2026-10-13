import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Tag } from 'lucide-react'
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
import { InvoiceTagRows } from '@/features/invoice-tags/invoice-tag-rows'
import { useInvoiceTagForm, type InvoiceTagFormValues } from '@/features/invoice-tags/use-invoice-tag-form'
import type { InvoiceTagDetailWithPermissions, InvoiceTagFormMode } from '@/features/invoice-tags/types'

interface InvoiceTagDetailViewProps {
  invoiceTag: InvoiceTagDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single invoice tag on the record kit: identity band, the
 * "Dettagli" rows, the activity in the side column, a metadata footer. There
 * is no edit page (wave 3 of the "no edit pages" plan): every row edits IN
 * PLACE, one at a time, each save a PATCH of that field alone.
 */
export function InvoiceTagDetailView(props: InvoiceTagDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.invoiceTag.permissions}>
      <InvoiceTagDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function InvoiceTagDetailContent({ invoiceTag, onChanged }: InvoiceTagDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<InvoiceTagFormMode>(() => ({ type: 'edit', invoiceTag }), [invoiceTag])
  const { form, onSubmit, serverError, clearServerError } = useInvoiceTagForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<InvoiceTagFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('invoiceTags.form.genericError'),
  })
  const canEdit = invoiceTag.permissions.resource.update
  const createdAt = formatDateTime(invoiceTag.created_at)
  const updatedAt = formatDateTime(invoiceTag.updated_at)
  const tabs: RecordCollaborationTab[] = invoiceTag.permissions.actions.view_activity
    ? [activityLogTab('invoice-tags', invoiceTag.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={invoiceTag.name} icon={<Tag />} />}
            title={invoiceTag.name}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('invoiceTags.form.sections.identity.title')} full>
              <RecordFieldList>
                <InvoiceTagRows inline={inline} canEdit={canEdit} />
                <RecordField label={t('invoiceTags.detail.sort_order')}>{invoiceTag.sort_order}</RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('invoiceTags.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('invoiceTags.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
