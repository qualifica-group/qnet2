import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Percent } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCardHeader,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import { formatDateTime } from '@/features/table/cell-renderers'
import { VatRateRows } from '@/features/vat-rates/vat-rate-rows'
import { useVatRateForm, type VatRateFormValues } from '@/features/vat-rates/use-vat-rate-form'
import type { VatRateDetailWithPermissions, VatRateFormMode } from '@/features/vat-rates/types'

interface VatRateDetailViewProps {
  vatRate: VatRateDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single VAT rate on the record kit: identity band, the rows,
 * the admin-defined custom fields, the activity card, a metadata footer.
 * There is no edit page (wave 3 of the "no edit pages" plan): every row
 * edits IN PLACE, one at a time, each save a PATCH of that field alone.
 */
export function VatRateDetailView(props: VatRateDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.vatRate.permissions}>
      <VatRateDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function VatRateDetailContent({ vatRate, onChanged }: VatRateDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<VatRateFormMode>(() => ({ type: 'edit', vatRate }), [vatRate])
  const { form, onSubmit, serverError, clearServerError } = useVatRateForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<VatRateFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('vatRates.form.genericError'),
  })
  const canEdit = vatRate.permissions.resource.update
  const createdAt = formatDateTime(vatRate.created_at)
  const canViewActivity = vatRate.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={canViewActivity ? [activityLogTab('vat-rates', vatRate.id, t('activityLog.title'))] : []}
        header={
          <RecordCardHeader
            media={
              <DetailMonogram
                name={vatRate.name}
                icon={<Percent />}
                className="size-10 text-base [&>svg]:size-5"
              />
            }
            title={vatRate.name}
            legacyId={vatRate.old_id}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('vatRates.detail.details')} full>
              <RecordFieldList>
                <VatRateRows inline={inline} canEdit={canEdit} />
              </RecordFieldList>
            </RecordSection>
            <CustomFieldRecordSections
              resource="vat-rates"
              values={vatRate.custom_fields ?? {}}
              inline={inline}
              control={form.control}
            />
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('vatRates.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
