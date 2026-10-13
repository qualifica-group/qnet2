import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Flag } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCardHeader,
  RecordField,
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
import { formatDateTime } from '@/features/table/cell-renderers'
import { ContractStatusRows } from '@/features/contract-statuses/contract-status-rows'
import { useContractStatusForm, type ContractStatusFormValues } from '@/features/contract-statuses/use-contract-status-form'
import type { ContractStatusDetailWithPermissions, ContractStatusFormMode } from '@/features/contract-statuses/types'

interface ContractStatusDetailViewProps {
  contractStatus: ContractStatusDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single contract status on the record kit: identity band, the
 * "Dettagli" rows, the activity in the side column, a metadata
 * footer. There is no edit page (spec 0235): every row edits IN PLACE, one at
 * a time, each save a PATCH of that field alone.
 */
export function ContractStatusDetailView(props: ContractStatusDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.contractStatus.permissions}>
      <ContractStatusDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function ContractStatusDetailContent({ contractStatus, onChanged }: ContractStatusDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<ContractStatusFormMode>(() => ({ type: 'edit', contractStatus }), [contractStatus])
  const { form, onSubmit, serverError, clearServerError } = useContractStatusForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<ContractStatusFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('contractStatuses.form.genericError'),
  })
  const canEdit = contractStatus.permissions.resource.update
  const createdAt = formatDateTime(contractStatus.created_at)
  const tabs = contractStatus.permissions.actions.view_activity
    ? [activityLogTab('contract-statuses', contractStatus.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader media={<DetailMonogram name={contractStatus.name} icon={<Flag />} />} title={contractStatus.name} />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('contractStatuses.form.sections.identity.title')} full>
              <RecordFieldList>
                <ContractStatusRows inline={inline} canEdit={canEdit} isSystemRow={contractStatus.system_key !== null} isCurrentDefault={contractStatus.is_default} />
                <RecordField label={t('contractStatuses.detail.sort_order')}>{contractStatus.sort_order}</RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('contractStatuses.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
