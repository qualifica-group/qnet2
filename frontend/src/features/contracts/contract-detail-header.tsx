import { FileText } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader } from '@/components/detail/record-panel'
import {
  ContractAlertBadge,
  ContractStatusBadge,
  ContractSuspendedBadge,
} from '@/features/contracts/contract-status-badges'
import type { ContractDetail } from '@/features/contracts/types'

interface ContractDetailHeaderProps {
  contract: ContractDetail
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
export function ContractDetailHeader({ contract }: ContractDetailHeaderProps) {
  return (
    <RecordCardHeader
      media={
        <DetailMonogram
          name={contract.quote.title}
          icon={<FileText />}
          className="size-10 text-base [&>svg]:size-5"
        />
      }
      title={contract.quote.title}
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
  )
}
