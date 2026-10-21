import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { FileSignature } from 'lucide-react'
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
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ContractTypeRows } from '@/features/contract-types/contract-type-rows'
import { useContractTypeForm, type ContractTypeFormValues } from '@/features/contract-types/use-contract-type-form'
import { CONTRACT_TYPES_DOMAIN, type ContractTypeDetail, type ContractTypeFormMode } from '@/features/contract-types/types'

interface ContractTypeDetailViewProps {
  contractType: ContractTypeDetail
  /** Called after an in-place save, so the host refreshes whatever lists the record. */
  onChanged?: () => void
}

/**
 * Detail of a single contract type on the record kit: identity band, the rows edited IN PLACE
 * one at a time (each save a PATCH of that field alone), the activity log and a metadata footer.
 */
export function ContractTypeDetailView(props: ContractTypeDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.contractType.permissions}>
      <ContractTypeDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function ContractTypeDetailContent({ contractType, onChanged }: ContractTypeDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<ContractTypeFormMode>(() => ({ type: 'edit', contractType }), [contractType])
  const { form, onSubmit, serverError, clearServerError } = useContractTypeForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<ContractTypeFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('contractTypes.form.genericError'),
  })
  const canEdit = contractType.permissions.resource.update
  const createdAt = formatDateTime(contractType.created_at)
  const updatedAt = formatDateTime(contractType.updated_at)
  const tabs: RecordCollaborationTab[] = contractType.permissions.actions.view_activity
    ? [activityLogTab(CONTRACT_TYPES_DOMAIN, contractType.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={contractType.name} icon={<FileSignature />} />}
            title={contractType.name}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('contractTypes.form.sections.identity.title')} full>
              <RecordFieldList>
                <ContractTypeRows inline={inline} canEdit={canEdit} />
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      {createdAt || updatedAt ? (
        <RecordMeta>
          {createdAt ? (
            <span>
              <span className="font-medium">{t('contractTypes.detail.created_at')}</span>{' '}
              <span aria-hidden="true">·</span> {createdAt}
            </span>
          ) : null}
          {updatedAt ? (
            <span>
              <span className="font-medium">{t('contractTypes.detail.updated_at')}</span>{' '}
              <span aria-hidden="true">·</span> {updatedAt}
            </span>
          ) : null}
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
