import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { FileText } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader } from '@/components/detail/record-panel'
import {
  ContractAlertBadge,
  ContractStatusBadge,
  ContractSuspendedBadge,
} from '@/features/contracts/contract-status-badges'
import { ContractTitleField } from '@/features/contracts/contract-fields'
import type { EditContractFormValues } from '@/features/contracts/contract-schema'
import type { ContractDetail } from '@/features/contracts/types'

interface ContractDetailHeaderProps {
  contract: ContractDetail
  /** The title edits in place (spec 0360); `canEdit` = `contracts.update` and an editable status group. */
  inline: InlineEdit
  control: Control<EditContractFormValues>
  canEdit: boolean
}

/**
 * Identity band of the contract record card: monogram, the QUOTE's title and
 * code (D-1 — a contract has none of its own), and the three status pills.
 *
 * No KPI strip follows it, unlike the offer's (user directive 2026-08-31): the
 * tinted band under the header belongs to the domain actions, which are many
 * and lifecycle-gated and need the room. The same directive took the economic
 * recap off this screen entirely — the amounts live on the Offerta.
 *
 * The actions are not in this header's own `actions` slot for the same reason:
 * up to six gated buttons would not fit beside the title.
 */
export function ContractDetailHeader({ contract, inline, control, canEdit }: ContractDetailHeaderProps) {
  const { t } = useTranslation()
  const editing = inline.editingField === 'title'

  // Open: the editor replaces the heading. Closed: the heading stays plain text (its accessible
  // name is the title alone) and the pencil sits in the header's actions.
  const titleField = (children: ReactNode | ((editAction: ReactNode) => ReactNode)) => (
    <RecordInlineField
      field="title"
      label={t('contracts.detail.title')}
      layout="block"
      className="mx-0"
      inline={inline}
      canEdit={canEdit}
      editor={<ContractTitleField control={control} />}
    >
      {children}
    </RecordInlineField>
  )

  return (
    // `group`: the pencil shows when the pointer is anywhere on the header.
    <div className="group">
      <RecordCardHeader
        media={
          <DetailMonogram
            name={contract.quote.title}
            icon={<FileText />}
            className="size-10 text-base [&>svg]:size-5"
          />
        }
        title={editing ? titleField(null) : contract.quote.title}
        actions={editing ? null : titleField((editAction) => editAction)}
        subtitle={contract.quote.code}
        badges={
          <>
            <ContractStatusBadge status={contract.contract_status} />
            {contract.is_suspended ? (
              <ContractSuspendedBadge
                suspendedAt={contract.suspended_at}
                previousStatus={contract.status_before_suspension}
              />
            ) : null}
            <ContractAlertBadge alert={contract.alert} />
          </>
        }
      />
    </div>
  )
}
